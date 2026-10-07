import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { computed, ref } from 'vue';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';

const filename = 'resources/js/pages/Production/ProductionPage.vue';
const source = readFileSync(new URL('../../' + filename, import.meta.url), 'utf8');
const { descriptor } = parse(source);

// 실제 페이지 스크립트를 실행하며 HTTP와 화면 이동만 대체합니다.
function createPage(get) {
  const calls = { complete: 0, cancel: 0, clear: 0, redirects: [], errors: [] };
  const script = descriptor.scriptSetup.content.replace(/^import[\s\S]*?;\s*/gm, '');
  const setup = new Function(
    'computed', 'ref', 'onMounted', 'onBeforeUnmount', 'useRouter',
    'useAppLoading', 'useSession', 'addLocalDays', 'toLocalDateString', 'window',
    script + '\nreturn { initializePage, loadPage, changeDate, disposePage, ready, loading, loadError, daily, appShellRef };',
  );
  const page = setup(
    computed, ref, () => {}, () => {},
    () => ({ replace: async (target) => calls.redirects.push(target) }),
    () => ({
      completePageLoading: async () => { calls.complete += 1; },
      cancelLoading: () => { calls.cancel += 1; },
    }),
    () => ({ clear: () => { calls.clear += 1; } }),
    (date, amount) => {
      const value = new Date(`${date}T12:00:00`);
      value.setDate(value.getDate() + amount);
      return value.toISOString().slice(0, 10);
    },
    () => '2026-10-04',
    { axios: { get } },
  );
  page.appShellRef.value = { setError: (message) => calls.errors.push(message) };
  return { page, calls };
}

// 인증·권한·서버 오류 응답을 Axios와 같은 형태로 만듭니다.
function httpError(status) {
  return { response: { status, data: { message: `서버 오류 ${status}` } } };
}

// 조회 가능한 점포를 포함하는 최소 선택 목록 응답입니다.
function optionsResponse() {
  return { data: { stores: [{ id: 7, name: '무역점' }], products: [], workers: [] } };
}

test('page script and template compile together', () => {
  const compiled = compileScript(descriptor, { id: 'production-loading-test' });
  const result = compileTemplate({
    source: descriptor.template.content,
    filename,
    id: 'production-loading-test',
    compilerOptions: { bindingMetadata: compiled.bindings },
  });
  assert.deepEqual(result.errors, []);
});

test('success requests options and daily once and finishes navigation loading', async () => {
  const requests = [];
  const { page, calls } = createPage(async (url, config) => {
    requests.push({ url, config });
    return url.endsWith('/options') ? optionsResponse() : { data: { rows: [{ id: 1 }] } };
  });
  await page.initializePage();
  assert.ok(requests.length >= 2);
  assert.equal(requests[1].config.params.store_id, 7);
  assert.equal(page.ready.value, true);
  assert.equal(page.loading.value, false);
  assert.equal(calls.complete, 1);
});

for (const failedEndpoint of ['options', 'daily']) {
  test(`401 from ${failedEndpoint} clears cached session and returns to login`, async () => {
    const { page, calls } = createPage(async (url) => {
      if (url.endsWith('/' + failedEndpoint)) throw httpError(401);
      return optionsResponse();
    });
    await page.initializePage();
    assert.equal(page.loading.value, false);
    assert.equal(page.ready.value, false);
    assert.equal(calls.clear, 1);
    assert.equal(calls.cancel, 1);
    assert.equal(calls.complete, 1);
    assert.deepEqual(calls.redirects, [{ name: 'login', query: { sessionExpired: '1' } }]);
  });
}

for (const status of [403, 500]) {
  test(`${status} is visible, ends loading and supports retry without logout`, async () => {
    let failing = true;
    const { page, calls } = createPage(async (url) => {
      if (failing) throw httpError(status);
      return url.endsWith('/options') ? optionsResponse() : { data: { rows: [] } };
    });
    await page.initializePage();
    assert.equal(page.loading.value, false);
    assert.equal(calls.complete, 1);
    assert.equal(calls.clear, 0);
    assert.equal(calls.redirects.length, 0);
    assert.equal(page.loadError.value, `서버 오류 ${status}`);
    assert.equal(calls.errors.length, 1);
    failing = false;
    await page.loadPage();
    assert.equal(page.ready.value, true);
    assert.equal(page.loadError.value, '');
  });
}

test('timeout is bounded and ends loading with a retry message', async () => {
  const { page, calls } = createPage(async (_url, config) => {
    assert.equal(config.timeout, 20000);
    throw { code: 'ECONNABORTED' };
  });
  await page.initializePage();
  assert.match(page.loadError.value, /응답 시간이 초과/);
  assert.equal(page.loading.value, false);
  assert.equal(calls.complete, 1);
});

test('no accessible store is an error instead of an empty successful screen', async () => {
  const { page, calls } = createPage(async () => ({ data: { stores: [] } }));
  await page.initializePage();
  assert.match(page.loadError.value, /점포가 없습니다/);
  assert.equal(page.ready.value, false);
  assert.equal(page.loading.value, false);
  assert.equal(calls.complete, 1);
});

test('late response cannot replace the newly selected date', async () => {
  let resolveOld;
  let first = true;
  const { page } = createPage(async (url, config) => {
    if (first) {
      first = false;
      return new Promise((resolve) => { resolveOld = resolve; });
    }
    return url.endsWith('/options')
      ? optionsResponse()
      : { data: { date: config.params.date, rows: [] } };
  });
  const old = page.loadPage();
  await page.changeDate('2026-10-05');
  resolveOld(optionsResponse());
  await old;
  assert.equal(page.daily.value.date, '2026-10-05');
  assert.equal(page.loading.value, false);
});

test('leaving the page suppresses a late 401 and does not finish another page loading', async () => {
  let rejectRequest;
  const { page, calls } = createPage(() => new Promise((_resolve, reject) => { rejectRequest = reject; }));
  const pending = page.initializePage();
  page.disposePage();
  rejectRequest(httpError(401));
  await pending;
  assert.equal(calls.redirects.length, 0);
  assert.equal(calls.complete, 0);
  assert.equal(calls.clear, 0);
});

<template><div class="d-flex align-center mb-2"><v-icon v-if="icon" :icon="icon" size="small" class="mr-2"/><div class="text-subtitle-2 font-weight-bold">{{ title }}</div><v-spacer/><slot/></div></template>
<script setup>defineProps({title:{type:String,required:true},icon:{type:String,default:''}});</script>

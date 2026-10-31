<script setup>
import { ref } from 'vue'
import AppModal from './AppModal.vue'
import AppButton from './AppButton.vue'
import ProductForm from './ProductForm.vue'

defineProps({ modelValue: Boolean, initialName: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue', 'created', 'draft-change'])
const editor = ref(null)
</script>

<template>
    <AppModal :model-value="modelValue" title="建立新產品" :busy="editor?.isCreating || editor?.isCreatingCategory" @update:model-value="editor?.close()">
        <ProductForm ref="editor" :model-value="modelValue" :initial-name="initialName" :show-actions="false"
            @update:model-value="emit('update:modelValue', $event)" @created="emit('created', $event)" @draft-change="emit('draft-change', $event)" />
        <template #footer>
            <AppButton variant="secondary" :disabled="editor?.isCreating || editor?.isCreatingCategory" @click="editor?.close()">取消</AppButton>
            <AppButton :disabled="!editor?.canCreate" :aria-busy="editor?.isCreating" @click="editor?.createProduct()">{{ editor?.isCreating ? '建立中…' : '建立產品' }}</AppButton>
        </template>
    </AppModal>
</template>

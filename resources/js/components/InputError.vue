<script setup lang="ts">
import { FieldError } from '@/components/ui/field';
defineProps<{ message?: string }>();
</script>

<template>
    <FieldError :errors="message ? [message] : []" />
</template>

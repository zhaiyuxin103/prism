<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <svg
        :class="$props.class"
        viewBox="0 0 32 32"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
    >
        <path
            d="M16 3 30 28H2L16 3Z"
            stroke="currentColor"
            stroke-width="2"
            stroke-linejoin="round"
            fill="none"
        />
        <path
            d="m16 3 4 25M2 28l15-10 13 10"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linejoin="round"
            fill="none"
        />
    </svg>
</template>

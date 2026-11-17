<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import type { HTMLAttributes } from 'vue';
import {
    InputGroup,
    InputGroupInput,
    InputGroupAddon,
    InputGroupButton,
} from '@/components/ui/input-group';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: HTMLAttributes['class'];
    autofocus?: boolean;
}>();

const showPassword = ref(false);
const inputRef = useTemplateRef('inputRef');

defineExpose({
    $el: inputRef,
    focus: () => inputRef.value?.$el?.focus(),
});
</script>

<template>
    <InputGroup>
        <InputGroupInput
            v-focus="props.autofocus"
            ref="inputRef"
            :type="showPassword ? 'text' : 'password'"
            :class="props.class"
            v-bind="$attrs"
        />
        <InputGroupAddon align="inline-end">
            <InputGroupButton
                type="button"
                size="icon-xs"
                @click="showPassword = !showPassword"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :tabindex="-1"
            >
                <EyeOff v-if="showPassword" /><Eye v-else />
            </InputGroupButton>
        </InputGroupAddon>
    </InputGroup>
</template>

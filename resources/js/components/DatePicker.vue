<script setup lang="ts">
import type { DateValue } from '@internationalized/date';
import { CalendarDays } from '@lucide/vue';
import { computed, ref, useTemplateRef, watch, watchEffect } from 'vue';
import { Calendar } from '@/components/ui/calendar';
import {
    InputGroup,
    InputGroupInput,
    InputGroupAddon,
    InputGroupButton,
} from '@/components/ui/input-group';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { parseCalendarDate } from '@/lib/date';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    id: string;
    name: string;
    label: string;
    defaultValue?: string;
    required?: boolean;
    disabled?: boolean;
}>();

const value = ref(props.defaultValue ?? '');
const open = ref(false);
const input = useTemplateRef('input');
const date = computed(() => parseCalendarDate(value.value));

watch(
    () => props.defaultValue,
    (next) => {
        value.value = next ?? '';
    },
);
watchEffect(() => {
    input.value?.$el?.setCustomValidity(
        value.value && !date.value ? 'Enter a valid date (YYYY-MM-DD).' : '',
    );
});

function selectDate(selected: DateValue | undefined): void {
    if (selected) {
        value.value = selected.toString();
        open.value = false;
    }
}
</script>

<template>
    <InputGroup>
        <InputGroupInput
            :id="id"
            ref="input"
            v-model="value"
            v-bind="$attrs"
            :name="name"
            :required="required"
            :disabled="disabled"
            type="text"
            placeholder="YYYY-MM-DD"
            maxlength="10"
            autocomplete="off"
        />
        <InputGroupAddon align="inline-end">
            <Popover v-model:open="open">
                <PopoverTrigger as-child>
                    <InputGroupButton
                        type="button"
                        size="icon-xs"
                        :disabled="disabled"
                        :aria-label="`Choose ${label}`"
                    >
                        <CalendarDays />
                    </InputGroupButton>
                </PopoverTrigger>
                <PopoverContent class="w-auto p-0" align="end">
                    <Calendar
                        :model-value="date"
                        :default-placeholder="date"
                        initial-focus
                        prevent-deselect
                        @update:model-value="selectDate"
                    />
                </PopoverContent>
            </Popover>
        </InputGroupAddon>
    </InputGroup>
</template>

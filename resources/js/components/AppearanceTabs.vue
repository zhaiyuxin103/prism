<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
function selectAppearance(value: unknown): void {
    if (value === 'light' || value === 'dark' || value === 'system') {
        updateAppearance(value);
    }
}
</script>

<template>
    <ToggleGroup
        type="single"
        variant="outline"
        :model-value="appearance"
        aria-label="Color theme"
        @update:model-value="selectAppearance"
    >
        <ToggleGroupItem
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            :value="value"
            :aria-label="label"
        >
            <component :is="Icon" aria-hidden="true" />{{ label }}
        </ToggleGroupItem>
    </ToggleGroup>
</template>

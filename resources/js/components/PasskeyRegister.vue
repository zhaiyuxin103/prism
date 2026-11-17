<script setup lang="ts">
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field, FieldLabel, FieldDescription } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

const emit = defineEmits<{
    success: [];
}>();

const getDefaultPasskeyName = () => {
    const ua = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    return [browser, os].filter(Boolean).join(' on ') || '';
};

const name = ref(getDefaultPasskeyName());
const showForm = ref(false);

const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        name.value = '';
        showForm.value = false;
        emit('success');
    },
});

const handleSubmit = async (event: Event) => {
    event.preventDefault();

    if (!name.value.trim()) {
        return;
    }

    await register(name.value);
};

const handleCancel = () => {
    showForm.value = false;
    name.value = '';
};
</script>

<template>
    <div v-if="!isSupported" class="text-sm text-muted-foreground">
        Passkeys are not supported in this browser.
    </div>

    <Button v-else-if="!showForm" variant="outline" @click="showForm = true">
        Add passkey
    </Button>

    <Card v-else
        ><CardContent>
            <form @submit="handleSubmit" class="flex flex-col gap-4">
                <Field :data-invalid="!!error">
                    <FieldLabel for="passkey-name">Passkey name</FieldLabel>
                    <Input
                        id="passkey-name"
                        type="text"
                        v-model="name"
                        placeholder="e.g., MacBook Pro, iPhone"
                        :aria-invalid="!!error"
                        aria-describedby="passkey-help passkey-error"
                        v-focus
                    />
                    <FieldDescription id="passkey-help"
                        >A name helps you identify this passkey
                        later.</FieldDescription
                    >
                </Field>

                <InputError id="passkey-error" :message="error ?? undefined" />

                <div class="flex gap-2">
                    <Button type="submit" :disabled="isLoading || !name.trim()">
                        {{ isLoading ? 'Registering...' : 'Register passkey' }}
                    </Button>
                    <Button type="button" variant="ghost" @click="handleCancel">
                        Cancel
                    </Button>
                </div>
            </form></CardContent
        ></Card
    >
</template>

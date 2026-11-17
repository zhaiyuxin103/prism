<script setup lang="ts">
import { Info } from '@lucide/vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: 'Log in' | 'Register';
};

defineProps<Props>();
</script>

<template>
    <div data-test="team-invitation-alert">
        <Alert variant="info">
            <Info class="size-4" />
            <AlertDescription>
                {{ action }} to join the "{{ invitation.teamName }}" team.
            </AlertDescription>
        </Alert>
    </div>
</template>

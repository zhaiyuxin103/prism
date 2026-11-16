<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property string $id
 * @property int|null $user_id
 * @property string $client_request_id
 * @property string $request_hash
 * @property array<array-key, mixed> $request_payload
 * @property string|null $provider_generation_id
 * @property \App\Enums\SubmissionState $submission_state
 * @property \App\Enums\GenerationStatus $status
 * @property string $estimated_provider_cost
 * @property string $estimated_user_cost
 * @property array<array-key, mixed> $estimate_snapshot
 * @property array<array-key, mixed>|null $provider_rates
 * @property array<array-key, mixed>|null $usage
 * @property string|null $image_url
 * @property \Carbon\CarbonImmutable|null $image_url_expires_at
 * @property \Carbon\CarbonImmutable|null $provider_created_at
 * @property \Carbon\CarbonImmutable|null $provider_started_at
 * @property \Carbon\CarbonImmutable|null $provider_completed_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submit_attempts
 * @property int $poll_attempts
 * @property \Carbon\CarbonImmutable|null $next_attempt_at
 * @property \Carbon\CarbonImmutable|null $last_checked_at
 * @property \Carbon\CarbonImmutable|null $capacity_wait_started_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\GenerationSettlement|null $settlement
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\GenerationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereCapacityWaitStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereClientRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereEstimateSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereEstimatedProviderCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereEstimatedUserCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereImageUrlExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereLastCheckedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereNextAttemptAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation wherePollAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereProviderCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereProviderCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereProviderGenerationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereProviderRates($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereProviderStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereRequestHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereRequestPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereSubmissionState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereSubmitAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereUsage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Generation whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGeneration {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $generation_id
 * @property string $outcome
 * @property string $currency
 * @property string $provider_cost
 * @property string $user_charge
 * @property int $markup_basis_points
 * @property string $calculation_version
 * @property \Carbon\CarbonImmutable $settled_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Generation $generation
 * @method static \Database\Factories\GenerationSettlementFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereCalculationVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereGenerationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereMarkupBasisPoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereOutcome($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereProviderCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereSettledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GenerationSettlement whereUserCharge($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperGenerationSettlement {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property \App\Enums\TeamRole $role
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Team|null $team
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Membership newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Membership newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Membership query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Membership whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Membership whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Membership whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Membership whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Membership whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Membership whereUserId($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperMembership {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TeamInvitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read \App\Models\Membership|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Membership> $memberships
 * @property-read int|null $memberships_count
 * @method static \Database\Factories\TeamFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereIsPersonal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team withoutTrashed()
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTeam {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $code
 * @property int $team_id
 * @property string $email
 * @property \App\Enums\TeamRole $role
 * @property int $invited_by
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property \Carbon\CarbonImmutable|null $accepted_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\User $inviter
 * @property-read \App\Models\Team|null $team
 * @method static \Database\Factories\TeamInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereInvitedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeamInvitation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperTeamInvitation {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property \Carbon\CarbonImmutable|null $two_factor_confirmed_at
 * @property int|null $current_team_id
 * @property bool $is_admin
 * @property-read \App\Models\Team|null $currentTeam
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $ownedTeams
 * @property-read int|null $owned_teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passkeys\Passkey> $passkeys
 * @property-read int|null $passkeys_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Membership> $teamMemberships
 * @property-read int|null $team_memberships_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperUser {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $provider_event_id
 * @property string $event_type
 * @property string|null $provider_generation_id
 * @property array<array-key, mixed> $payload
 * @property string $processing_status
 * @property int $attempts
 * @property \Carbon\CarbonImmutable|null $next_attempt_at
 * @property string|null $last_error
 * @property \Carbon\CarbonImmutable $received_at
 * @property \Carbon\CarbonImmutable|null $processed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Database\Factories\WebhookEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereNextAttemptAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProcessingStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProviderEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProviderGenerationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereUpdatedAt($value)
 * @mixin \Eloquent
 */
	#[\AllowDynamicProperties]
	class IdeHelperWebhookEvent {}
}


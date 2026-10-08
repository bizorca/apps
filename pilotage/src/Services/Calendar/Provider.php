<?php

declare(strict_types=1);

namespace Bizorca\Pilotage\Services\Calendar;

/**
 * What a calendar provider has to be able to do.
 *
 * Two implementations, Google and Microsoft. They are close enough to share a
 * shape and different enough that pretending otherwise would cost more than the
 * interface does — Google returns a syncToken, Microsoft a deltaLink; Google
 * calls a deleted event "cancelled", Microsoft removes it from the delta with
 * an @removed annotation; the field names differ throughout.
 *
 * Everything here is a plain array in and out. No provider type leaks past this
 * boundary, so CalendarSync reads the same either way and a third provider is a
 * new file rather than a set of conditionals.
 */
interface Provider
{
    public function key(): string;

    public function label(): string;

    /** Where to send someone to authorise. */
    public function authorizeUrl(string $state, string $redirectUri, ?string $challenge = null): string;

    /**
     * Swap an authorisation code for tokens.
     *
     * @return array{access_token:string, refresh_token:?string, expires_in:int, scope:?string}|null
     */
    public function exchangeCode(string $code, string $redirectUri, ?string $verifier = null): ?array;

    /**
     * @return array{access_token:string, expires_in:int}|null
     */
    public function refresh(string $refreshToken): ?array;

    /** The address they connected as, for showing back to them. */
    public function accountEmail(string $accessToken): ?string;

    /**
     * Create or update one event.
     *
     * @param array<string,mixed> $event Normalised: title, description,
     *        starts_at, ends_at, location, timezone.
     * @return array{id:string, etag:?string, updated_at:?string}|null
     */
    public function upsertEvent(string $accessToken, ?string $calendarId, ?string $remoteId, array $event): ?array;

    public function deleteEvent(string $accessToken, ?string $calendarId, string $remoteId): bool;

    /**
     * Everything that changed since the last sync.
     *
     * @return array{events:array<int,array<string,mixed>>, sync_token:?string, reset:bool}|null
     *         `reset` means the token expired and the caller should treat this
     *         as a full resync rather than a delta — providers expire them and
     *         a caller that ignores it silently stops seeing changes.
     */
    public function changes(string $accessToken, ?string $calendarId, ?string $syncToken): ?array;
}

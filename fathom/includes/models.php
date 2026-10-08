<?php
/**
 * Just enough of Eloquent for Fathom: row objects with casts, lazy cached
 * relations, soft-delete scoping, create/update/delete with timestamps, and a
 * Collection with the dozen methods the templates call.
 *
 * Eloquent behaviour that matters and is reproduced on purpose:
 *  - Soft-deleted rows (deleted_at set) are invisible to find() and to every
 *    relation, including belongsTo: a comment by a removed member has no
 *    creator, exactly as before.
 *  - update() is a no-op when nothing changed, so updated_at only moves on a
 *    real change (stalled-card and auto-postpone logic read updated_at).
 *  - Ids are UUID strings generated on create; positions default to max + 1.
 */

declare(strict_types=1);

/* ─────────────────────────────────────────────────────────── collection ── */

final class FmCollection implements IteratorAggregate, Countable, JsonSerializable
{
    /** @param array<int|string, mixed> $items */
    public function __construct(private array $items = []) {}

    public function getIterator(): ArrayIterator { return new ArrayIterator($this->items); }
    public function count(): int { return count($this->items); }
    public function all(): array { return $this->items; }
    public function isEmpty(): bool { return $this->items === []; }
    public function isNotEmpty(): bool { return $this->items !== []; }
    public function first(): mixed { return $this->items === [] ? null : reset($this->items); }
    public function take(int $n): self { return new self(array_slice($this->items, 0, $n)); }
    public function values(): self { return new self(array_values($this->items)); }

    private static function get(mixed $item, string $key): mixed
    {
        return is_array($item) ? ($item[$key] ?? null) : $item->$key;
    }

    public function pluck(string $key): self
    {
        return new self(array_map(fn($i) => self::get($i, $key), array_values($this->items)));
    }

    public function where(string $key, mixed $value): self
    {
        return new self(array_values(array_filter($this->items, fn($i) => self::get($i, $key) == $value)));
    }

    public function whereNotIn(string $key, iterable $values): self
    {
        $vals = $values instanceof self ? $values->all() : (array) $values;
        return new self(array_values(array_filter($this->items, fn($i) => !in_array(self::get($i, $key), $vals, true))));
    }

    public function filter(?callable $fn = null): self
    {
        return new self(array_values(array_filter($this->items, $fn ?? fn($i) => (bool) $i)));
    }

    public function map(callable $fn): self { return new self(array_map($fn, $this->items)); }

    /** @return array<string, self> */
    public function groupBy(string|callable $key): array
    {
        $out = [];
        foreach ($this->items as $i) {
            $k = is_callable($key) ? $key($i) : self::get($i, $key);
            $out[(string) $k][] = $i;
        }
        return array_map(fn($g) => new self($g), $out);
    }

    public function toArray(): array
    {
        return array_map(fn($i) => $i instanceof JsonSerializable ? $i->jsonSerialize() : ($i instanceof self ? $i->toArray() : $i), $this->items);
    }

    public function jsonSerialize(): array { return $this->toArray(); }
    public function toJson(): string { return (string) json_encode($this->toArray()); }
    public function toArrayValues(): array { return array_values($this->items); }
}

/* ─────────────────────────────────────────────────────────── base model ── */

abstract class FmModel implements JsonSerializable
{
    public const TABLE = '';
    public const SOFT  = false;
    public const DATES = [];
    public const BOOLS = [];
    public const INTS  = [];
    public const JSON  = [];
    public const TIMESTAMPS = true;
    /** Laravel morph type string, where the model is polymorphic. */
    public const MORPH = '';

    /** @var array<string, mixed> */
    public array $attributes = [];
    /** @var array<string, mixed> */
    private array $relCache = [];

    final public function __construct(array $row = [])
    {
        $this->attributes = $row;
    }

    public static function hydrate(array $rows): FmCollection
    {
        return new FmCollection(array_map(fn($r) => new static($r), $rows));
    }

    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->cast($key, $this->attributes[$key]);
        }
        $method = 'rel' . str_replace('_', '', ucwords($key, '_'));
        if (method_exists($this, $method)) {
            if (!array_key_exists($key, $this->relCache)) {
                $this->relCache[$key] = $this->$method();
            }
            return $this->relCache[$key];
        }
        return null;
    }

    public function __isset(string $key): bool
    {
        return $this->__get($key) !== null;
    }

    /** Pre-load a relation, the way an eager load with constraints did. */
    public function setRelation(string $key, mixed $value): static
    {
        $this->relCache[$key] = $value;
        return $this;
    }

    public function forget(?string $rel = null): static
    {
        if ($rel === null) {
            $this->relCache = [];
        } else {
            unset($this->relCache[$rel]);
        }
        return $this;
    }

    protected function cast(string $key, mixed $v): mixed
    {
        if ($v === null) {
            return null;
        }
        if (in_array($key, static::DATES, true) || in_array($key, ['created_at', 'updated_at', 'deleted_at'], true)) {
            return new FmDate((string) $v);
        }
        if (in_array($key, static::BOOLS, true)) {
            return (bool) (int) $v;
        }
        if (in_array($key, static::INTS, true)) {
            return (int) $v;
        }
        if (in_array($key, static::JSON, true)) {
            return is_array($v) ? $v : (json_decode((string) $v, true) ?? []);
        }
        return $v;
    }

    /* ── reads ─────────────────────────────────────────────────────────── */

    protected static function scope(bool $withTrashed = false): string
    {
        return static::SOFT && !$withTrashed ? ' AND deleted_at IS NULL' : '';
    }

    public static function find(?string $id, bool $withTrashed = false): ?static
    {
        if ($id === null || $id === '') {
            return null;
        }
        $s = tl_db()->prepare('SELECT * FROM ' . static::TABLE . ' WHERE id = ?' . static::scope($withTrashed) . ' LIMIT 1');
        $s->execute([$id]);
        $row = $s->fetch();
        return $row ? new static($row) : null;
    }

    public static function findOrFail(?string $id): static
    {
        return static::find($id) ?? abort(404);
    }

    /** SELECT * FROM table WHERE <sql> [+ soft scope]. */
    public static function where(string $sql = '1=1', array $params = [], string $tail = ''): FmCollection
    {
        $s = tl_db()->prepare('SELECT * FROM ' . static::TABLE . ' WHERE (' . $sql . ')' . static::scope() . ' ' . $tail);
        $s->execute($params);
        return static::hydrate($s->fetchAll());
    }

    public static function firstWhere(string $sql, array $params = []): ?static
    {
        return static::where($sql, $params, 'LIMIT 1')->first();
    }

    public static function countWhere(string $sql = '1=1', array $params = []): int
    {
        $s = tl_db()->prepare('SELECT COUNT(*) FROM ' . static::TABLE . ' WHERE (' . $sql . ')' . static::scope());
        $s->execute($params);
        return (int) $s->fetchColumn();
    }

    /* ── writes ────────────────────────────────────────────────────────── */

    /** Insert with a new UUID and Laravel's created_at/updated_at. */
    public static function create(array $data): static
    {
        $data = array_merge(['id' => fm_uuid()], $data);
        if (static::TIMESTAMPS) {
            $now = now_sql();
            $data += ['created_at' => $now, 'updated_at' => $now];
        }
        $data = static::toDb($data);
        $cols = array_keys($data);
        tl_db()->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::TABLE,
            implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
            implode(', ', array_fill(0, count($cols), '?'))
        ))->execute(array_values($data));
        return static::find($data['id'], true) ?? new static($data);
    }

    /** Update changed columns only; no change means no write and no new updated_at. */
    public function update(array $data): void
    {
        $data  = static::toDb($data);
        $dirty = [];
        foreach ($data as $k => $v) {
            $old = $this->attributes[$k] ?? null;
            if ($old === null ? $v !== null : ($v === null || (string) $old !== (string) $v)) {
                $dirty[$k] = $v;
            }
        }
        if ($dirty === []) {
            return;
        }
        if (static::TIMESTAMPS && !array_key_exists('updated_at', $dirty)) {
            $dirty['updated_at'] = now_sql();
        }
        $sets = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($dirty)));
        tl_db()->prepare('UPDATE ' . static::TABLE . " SET {$sets} WHERE id = ?")
               ->execute([...array_values($dirty), $this->attributes['id']]);
        $this->attributes = array_merge($this->attributes, $dirty);
        $this->relCache = [];
    }

    public function delete(): void
    {
        if (static::SOFT) {
            $now = now_sql();
            tl_db()->prepare('UPDATE ' . static::TABLE . ' SET deleted_at = ?' . (static::TIMESTAMPS ? ', updated_at = ?' : '') . ' WHERE id = ?')
                   ->execute(static::TIMESTAMPS ? [$now, $now, $this->attributes['id']] : [$now, $this->attributes['id']]);
            $this->attributes['deleted_at'] = $now;
            return;
        }
        tl_db()->prepare('DELETE FROM ' . static::TABLE . ' WHERE id = ?')->execute([$this->attributes['id']]);
    }

    protected static function toDb(array $data): array
    {
        foreach ($data as $k => $v) {
            if ($v instanceof FmDate) {
                $data[$k] = (string) $v;
            } elseif (is_bool($v)) {
                $data[$k] = $v ? 1 : 0;
            } elseif (in_array($k, static::JSON, true) && is_array($v)) {
                $data[$k] = json_encode($v);
            }
        }
        return $data;
    }

    /** Laravel's toArray(): attributes with casts applied, dates as ISO-8601. */
    public function jsonSerialize(): array
    {
        $out = [];
        foreach ($this->attributes as $k => $v) {
            $c = $this->cast($k, $v);
            $out[$k] = $c instanceof FmDate ? $c->jsonSerialize() : $c;
        }
        return $out;
    }

    public function is(?FmModel $other): bool
    {
        return $other !== null && $other::TABLE === static::TABLE && $other->id === $this->id;
    }
}

/* ─────────────────────────────────────────────────────────── entities ──── */

final class Account extends FmModel
{
    public const TABLE = 'fm_accounts';
    public const SOFT  = true;
    public const DATES = ['cancelled_at'];
    public const JSON  = ['settings'];

    public function isCancelled(): bool { return $this->attributes['cancelled_at'] !== null; }

    public function cancel(): void { $this->update(['cancelled_at' => now_sql()]); }

    protected function relUsers(): FmCollection
    {
        return FmUser::forAccount($this->id);
    }

    public function boardsCount(): int { return Board::countWhere('account_id = ?', [$this->id]); }
    public function cardsCount(): int { return Card::countWhere('account_id = ?', [$this->id]); }
    public function clientsCount(): int { return Client::countWhere('account_id = ?', [$this->id]); }
    public function usersCount(): int { return FmUser::countWhere('account_id = ?', [$this->id]); }
    public function exportsCount(): int { return Export::countWhere('account_id = ?', [$this->id]); }
    public function cardTemplatesCount(): int { return CardTemplate::countWhere('account_id = ?', [$this->id]); }
    public function tagsCount(): int { return Tag::countWhere('account_id = ?', [$this->id]); }
}

/**
 * A person's membership in one Fathom account. Name and email live on the
 * shared `users` row and are joined in, so $user->name and
 * $user->email_address read as they did in the original.
 */
final class FmUser extends FmModel
{
    public const TABLE = 'fm_users';
    public const SOFT  = true;
    public const BOOLS = ['is_sysop', 'notification_digest'];
    public const MORPH = 'App\\Models\\User';

    private const SELECT = 'SELECT f.*, u.name AS name, u.email AS email_address
                              FROM fm_users f JOIN users u ON u.id = f.user_id';

    public static function find(?string $id, bool $withTrashed = false): ?static
    {
        if ($id === null || $id === '') {
            return null;
        }
        $s = tl_db()->prepare(self::SELECT . ' WHERE f.id = ?' . ($withTrashed ? '' : ' AND f.deleted_at IS NULL') . ' LIMIT 1');
        $s->execute([$id]);
        $row = $s->fetch();
        return $row ? new static($row) : null;
    }

    public static function where(string $sql = '1=1', array $params = [], string $tail = ''): FmCollection
    {
        $s = tl_db()->prepare(self::SELECT . ' WHERE (' . $sql . ') AND f.deleted_at IS NULL ' . $tail);
        $s->execute($params);
        return static::hydrate($s->fetchAll());
    }

    public static function countWhere(string $sql = '1=1', array $params = []): int
    {
        $s = tl_db()->prepare('SELECT COUNT(*) FROM fm_users f WHERE (' . $sql . ') AND f.deleted_at IS NULL');
        $s->execute($params);
        return (int) $s->fetchColumn();
    }

    /** The membership for a shared-account user id, live or removed. */
    public static function forSharedUser(int $userId, bool $withTrashed = false): ?self
    {
        $s = tl_db()->prepare(self::SELECT . ' WHERE f.user_id = ?' . ($withTrashed ? '' : ' AND f.deleted_at IS NULL') . ' LIMIT 1');
        $s->execute([$userId]);
        $row = $s->fetch();
        return $row ? new self($row) : null;
    }

    public static function forAccount(string $accountId): FmCollection
    {
        return self::where('f.account_id = ?', [$accountId], 'ORDER BY u.name');
    }

    /** name/email are not columns of fm_users, so never write them. */
    public function update(array $data): void
    {
        unset($data['name'], $data['email_address']);
        parent::update($data);
    }

    public function isAdmin(): bool { return $this->attributes['role'] === 'admin'; }
    public function isMember(): bool { return $this->attributes['role'] === 'member'; }
    public function isSysop(): bool { return (bool) $this->attributes['is_sysop']; }

    public function initials(): string
    {
        $name  = (string) $this->name;
        $parts = explode(' ', $name);
        if (count($parts) >= 2) {
            return strtoupper($parts[0][0] . end($parts)[0]);
        }
        return strtoupper(substr($name, 0, 2));
    }

    protected function relAccount(): ?Account { return Account::find($this->account_id); }

    public function isWatching(Card $card): bool
    {
        return Watch::countWhere('user_id = ? AND card_id = ?', [$this->id, $card->id]) > 0;
    }

    public function hasPinned(Card $card): bool
    {
        return Pin::countWhere('user_id = ? AND card_id = ?', [$this->id, $card->id]) > 0;
    }

    public function unreadNotificationsCount(): int
    {
        return Notification::countWhere('user_id = ? AND read_at IS NULL', [$this->id]);
    }

    public function hasUnreadNotifications(): bool { return $this->unreadNotificationsCount() > 0; }
}

final class Board extends FmModel
{
    public const TABLE = 'fm_boards';
    public const SOFT  = true;
    public const DATES = ['archived_at'];
    public const BOOLS = ['is_public', 'auto_postpone'];
    public const INTS  = ['postpone_days'];
    public const MORPH = 'App\\Models\\Board';

    public static function create(array $data): static
    {
        $data['share_token'] ??= Str::random(32);
        return parent::create($data);
    }

    protected function relColumns(): FmCollection
    {
        return Column::where('board_id = ?', [$this->id], 'ORDER BY position');
    }

    protected function relCreator(): ?FmUser { return FmUser::find($this->creator_id); }
    protected function relAccount(): ?Account { return Account::find($this->account_id); }

    public function isArchived(): bool { return $this->attributes['archived_at'] !== null; }

    public function openCardsCount(): int
    {
        return Card::countWhere('board_id = ? AND closed_at IS NULL', [$this->id]);
    }

    public function defaultColumn(): ?Column { return $this->columns->first(); }

    public function publicUrl(): string { return route('public.boards.show', $this->share_token); }

    /**
     * Send open cards untouched for postpone_days back to the first column.
     * Runs on board page load (no cron). Returns how many moved.
     */
    public function runAutoPostpone(): int
    {
        if (!$this->auto_postpone) {
            return 0;
        }
        $first = Column::where('board_id = ?', [$this->id], 'ORDER BY position LIMIT 1')->first();
        if (!$first) {
            return 0;
        }
        $days      = $this->postpone_days ?? 30;
        $threshold = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $cards = Card::where(
            'board_id = ? AND closed_at IS NULL AND (column_id IS NULL OR column_id != ?) AND updated_at < ?',
            [$this->id, $first->id, $threshold]
        );
        foreach ($cards as $card) {
            $card->update(['column_id' => $first->id, 'position' => Card::maxPosition($first->id) + 1]);
        }
        return count($cards);
    }

    /**
     * Mark open cards stalled when they have comments or assignees and no
     * update in 14 days; clear the mark once they move again. Written straight
     * to the table so updated_at is not touched (the original used DB::table).
     */
    public function refreshStalledCards(): void
    {
        $threshold = gmdate('Y-m-d H:i:s', time() - 14 * 86400);
        $db = tl_db();
        $db->prepare(
            'UPDATE fm_cards c SET stalled_at = ?
              WHERE c.board_id = ? AND c.closed_at IS NULL AND c.deleted_at IS NULL AND c.stalled_at IS NULL
                AND c.updated_at < ?
                AND (EXISTS (SELECT 1 FROM fm_comments m WHERE m.card_id = c.id)
                  OR EXISTS (SELECT 1 FROM fm_assignments a WHERE a.card_id = c.id))'
        )->execute([now_sql(), $this->id, $threshold]);
        $db->prepare(
            'UPDATE fm_cards SET stalled_at = NULL
              WHERE board_id = ? AND closed_at IS NULL AND deleted_at IS NULL AND stalled_at IS NOT NULL
                AND updated_at >= ?'
        )->execute([$this->id, $threshold]);
    }
}

final class Column extends FmModel
{
    public const TABLE = 'fm_columns';
    public const INTS  = ['position', 'cards_limit'];

    public static function create(array $data): static
    {
        if (!isset($data['position'])) {
            $s = tl_db()->prepare('SELECT MAX(position) FROM fm_columns WHERE board_id = ?');
            $s->execute([$data['board_id']]);
            $data['position'] = (int) $s->fetchColumn() + 1;
        }
        return parent::create($data);
    }

    protected function relBoard(): ?Board { return Board::find($this->board_id); }

    /** All cards in position order; the board page swaps in open-only via setRelation(). */
    protected function relCards(): FmCollection
    {
        return Card::where('column_id = ?', [$this->id], 'ORDER BY position');
    }

    /** Deleting a column leaves its cards with no column, as before. */
    public function delete(): void
    {
        tl_db()->prepare('UPDATE fm_cards SET column_id = NULL, updated_at = ? WHERE column_id = ? AND deleted_at IS NULL')
               ->execute([now_sql(), $this->id]);
        parent::delete();
    }

    public function moveLeft(): void { $this->swapWith($this->position - 1); }
    public function moveRight(): void { $this->swapWith($this->position + 1); }

    private function swapWith(int $pos): void
    {
        $sibling = Column::firstWhere('board_id = ? AND position = ?', [$this->board_id, $pos]);
        if ($sibling) {
            $mine = $this->position;
            $sibling->update(['position' => $mine]);
            $this->update(['position' => $pos]);
        }
    }

    public function isAtLimit(): bool
    {
        if (!$this->cards_limit) {
            return false;
        }
        return Card::countWhere('column_id = ? AND closed_at IS NULL', [$this->id]) >= $this->cards_limit;
    }
}

final class Card extends FmModel
{
    public const TABLE = 'fm_cards';
    public const SOFT  = true;
    public const DATES = ['closed_at', 'stalled_at', 'due_at'];
    public const BOOLS = ['is_draft', 'is_golden'];
    public const INTS  = ['position'];
    public const MORPH = 'App\\Models\\Card';

    public static function maxPosition(?string $columnId): int
    {
        $s = tl_db()->prepare('SELECT MAX(position) FROM fm_cards WHERE ' . ($columnId === null ? 'column_id IS NULL' : 'column_id = ?') . ' AND deleted_at IS NULL');
        $s->execute($columnId === null ? [] : [$columnId]);
        return (int) $s->fetchColumn();
    }

    public static function create(array $data): static
    {
        $data['share_token'] ??= Str::random(32);
        if (!isset($data['position'])) {
            $data['position'] = self::maxPosition($data['column_id'] ?? null) + 1;
        }
        return parent::create($data);
    }

    protected function relBoard(): ?Board { return Board::find($this->board_id); }
    protected function relColumn(): ?Column { return Column::find($this->column_id); }
    protected function relCreator(): ?FmUser { return FmUser::find($this->creator_id); }
    protected function relAccount(): ?Account { return Account::find($this->account_id); }

    protected function relComments(): FmCollection
    {
        return Comment::where('card_id = ?', [$this->id], 'ORDER BY created_at');
    }

    protected function relAssignments(): FmCollection
    {
        return Assignment::where('card_id = ?', [$this->id]);
    }

    protected function relAssignees(): FmCollection
    {
        return FmUser::where('f.id IN (SELECT user_id FROM fm_assignments WHERE card_id = ?)', [$this->id]);
    }

    protected function relSteps(): FmCollection
    {
        return Step::where('card_id = ?', [$this->id], 'ORDER BY position');
    }

    protected function relTags(): FmCollection
    {
        return Tag::where(
            'id IN (SELECT tag_id FROM fm_taggings WHERE taggable_id = ? AND taggable_type = ?)',
            [$this->id, self::MORPH]
        );
    }

    protected function relReactions(): FmCollection
    {
        return Reaction::where('reactable_id = ? AND reactable_type = ?', [$this->id, self::MORPH]);
    }

    protected function relClosure(): ?CardClosure { return CardClosure::firstWhere('card_id = ?', [$this->id]); }

    protected function relClients(): FmCollection
    {
        return Client::where('id IN (SELECT client_id FROM fm_client_cards WHERE card_id = ?)', [$this->id]);
    }

    public function isOpen(): bool { return $this->attributes['closed_at'] === null; }
    public function isClosed(): bool { return !$this->isOpen(); }
    public function isDraft(): bool { return (bool) $this->is_draft; }
    public function isGolden(): bool { return (bool) $this->is_golden; }
    public function isStalled(): bool { return $this->attributes['stalled_at'] !== null; }

    public function completedStepsCount(): int
    {
        return Step::countWhere('card_id = ? AND completed = 1', [$this->id]);
    }

    public function totalStepsCount(): int
    {
        return Step::countWhere('card_id = ?', [$this->id]);
    }

    public function close(FmUser $closer, ?string $reason = null): void
    {
        $this->update(['closed_at' => now_sql()]);
        CardClosure::create(['card_id' => $this->id, 'user_id' => $closer->id, 'reason' => $reason]);
    }

    public function reopen(): void
    {
        $this->update(['closed_at' => null]);
        tl_db()->prepare('DELETE FROM fm_closures WHERE card_id = ?')->execute([$this->id]);
    }

    public function publicUrl(): string { return route('public.cards.show', $this->share_token); }

    /** Replace this card's tags with $tagIds (Eloquent's sync on the morph pivot). */
    public function syncTags(array $tagIds): void
    {
        $db = tl_db();
        $current = $this->tags->pluck('id')->all();
        foreach (array_diff($current, $tagIds) as $gone) {
            $db->prepare('DELETE FROM fm_taggings WHERE tag_id = ? AND taggable_id = ? AND taggable_type = ?')
               ->execute([$gone, $this->id, self::MORPH]);
        }
        foreach (array_diff($tagIds, $current) as $new) {
            Tagging::create(['tag_id' => $new, 'taggable_id' => $this->id, 'taggable_type' => self::MORPH]);
        }
        $this->forget('tags');
    }

    public function duplicate(FmUser $copier): self
    {
        $copy = self::create([
            'account_id'  => $this->account_id,
            'board_id'    => $this->board_id,
            'column_id'   => $this->column_id,
            'creator_id'  => $copier->id,
            'title'       => 'Copy of ' . $this->title,
            'description' => $this->description,
            'color'       => $this->color,
            'is_draft'    => $this->attributes['is_draft'],
            'is_golden'   => $this->attributes['is_golden'],
            'due_at'      => $this->attributes['due_at'],
        ]);
        $copy->syncTags($this->tags->pluck('id')->all());
        foreach ($this->assignees as $assignee) {
            Assignment::create(['card_id' => $copy->id, 'user_id' => $assignee->id, 'assigner_id' => $copier->id]);
        }
        foreach ($this->steps as $step) {
            Step::create(['card_id' => $copy->id, 'title' => $step->title, 'position' => $step->position, 'completed' => 0]);
        }
        return $copy;
    }
}

final class Step extends FmModel
{
    public const TABLE = 'fm_steps';
    public const DATES = ['completed_at'];
    public const BOOLS = ['completed'];
    public const INTS  = ['position'];

    public static function create(array $data): static
    {
        if (!isset($data['position'])) {
            $s = tl_db()->prepare('SELECT MAX(position) FROM fm_steps WHERE card_id = ?');
            $s->execute([$data['card_id']]);
            $data['position'] = (int) $s->fetchColumn() + 1;
        }
        return parent::create($data);
    }

    public function complete(FmUser $user): void
    {
        $this->update(['completed' => 1, 'completed_at' => now_sql(), 'completed_by' => $user->id]);
    }

    public function incomplete(): void
    {
        $this->update(['completed' => 0, 'completed_at' => null, 'completed_by' => null]);
    }
}

final class Comment extends FmModel
{
    public const TABLE = 'fm_comments';
    public const SOFT  = true;
    public const MORPH = 'App\\Models\\Comment';

    /** A team member who comments starts watching the card. */
    public static function create(array $data): static
    {
        $comment = parent::create($data);
        if (!empty($data['creator_id'])) {
            Watch::firstOrCreate($data['creator_id'], $data['card_id']);
        }
        return $comment;
    }

    protected function relCard(): ?Card { return Card::find($this->card_id); }
    protected function relCreator(): ?FmUser { return FmUser::find($this->creator_id); }
    protected function relClient(): ?Client { return Client::find($this->client_id); }

    protected function relReactions(): FmCollection
    {
        return Reaction::where('reactable_id = ? AND reactable_type = ?', [$this->id, self::MORPH]);
    }

    public function creatorName(): string
    {
        return $this->creator?->name ?? $this->client?->name ?? 'Unknown';
    }

    public function creatorInitials(): string
    {
        if ($this->creator) {
            return $this->creator->initials();
        }
        if ($this->client) {
            return $this->client->initials();
        }
        return '?';
    }

    public function isEditableBy(FmUser|Client $actor): bool
    {
        if ($actor instanceof Client) {
            return $this->client_id === $actor->id;
        }
        return $this->creator_id === $actor->id || $actor->isAdmin();
    }

    /** The HTML a comment shows: stored render, or escaped text with line breaks. */
    public function html(): string
    {
        $html = (string) $this->body_html;
        return $html !== '' ? $html : nl2br(e($this->body));
    }
}

final class Tag extends FmModel
{
    public const TABLE = 'fm_tags';

    public function cardsCount(): int
    {
        $s = tl_db()->prepare(
            'SELECT COUNT(*) FROM fm_taggings t JOIN fm_cards c ON c.id = t.taggable_id
              WHERE t.tag_id = ? AND t.taggable_type = ? AND c.deleted_at IS NULL'
        );
        $s->execute([$this->id, Card::MORPH]);
        return (int) $s->fetchColumn();
    }
}

final class Tagging extends FmModel { public const TABLE = 'fm_taggings'; }

final class Reaction extends FmModel
{
    public const TABLE = 'fm_reactions';
    protected function relUser(): ?FmUser { return FmUser::find($this->user_id); }
}

final class Assignment extends FmModel
{
    public const TABLE = 'fm_assignments';
    protected function relUser(): ?FmUser { return FmUser::find($this->user_id); }
}

final class CardClosure extends FmModel
{
    public const TABLE = 'fm_closures';
    protected function relUser(): ?FmUser { return FmUser::find($this->user_id); }
}

final class Watch extends FmModel
{
    public const TABLE = 'fm_watches';

    public static function firstOrCreate(string $userId, string $cardId): void
    {
        if (self::countWhere('user_id = ? AND card_id = ?', [$userId, $cardId]) === 0) {
            self::create(['user_id' => $userId, 'card_id' => $cardId]);
        }
    }
}

final class Pin extends FmModel
{
    public const TABLE = 'fm_pins';
    protected function relCard(): ?Card { return Card::find($this->card_id); }
}

final class Mention extends FmModel { public const TABLE = 'fm_mentions'; }

final class Notification extends FmModel
{
    public const TABLE = 'fm_notifications';
    public const DATES = ['read_at', 'emailed_at'];

    protected function relCard(): ?Card { return Card::find($this->card_id); }
    public function isUnread(): bool { return $this->attributes['read_at'] === null; }
    public function isRead(): bool { return !$this->isUnread(); }
    public function markAsRead(): void { $this->update(['read_at' => now_sql()]); }
}

final class Event extends FmModel
{
    public const TABLE = 'fm_events';
    public const TIMESTAMPS = false;
    public const JSON  = ['metadata'];

    protected function relUser(): ?FmUser { return FmUser::find($this->user_id); }
    protected function relCard(): ?Card { return Card::find($this->card_id); }
}

final class Filter extends FmModel
{
    public const TABLE = 'fm_filters';
    public const JSON  = ['params'];
}

final class Access extends FmModel
{
    public const TABLE = 'fm_accesses';
    public const DATES = ['last_accessed_at'];

    /** updateOrCreate keyed on (user, board). */
    public static function touchFor(string $userId, string $boardId, array $values): void
    {
        $row = self::firstWhere('user_id = ? AND board_id = ?', [$userId, $boardId]);
        if ($row) {
            $row->update($values);
        } else {
            self::create(['user_id' => $userId, 'board_id' => $boardId] + $values);
        }
    }

    protected function relBoard(): ?Board { return Board::find($this->board_id); }
}

final class MagicLink extends FmModel
{
    public const TABLE = 'fm_magic_links';
    public const DATES = ['expires_at', 'used_at'];

    /** Invalidate open links for the client, then issue a fresh 64-char token. */
    public static function createForClient(Client $client): self
    {
        tl_db()->prepare(
            'UPDATE fm_magic_links SET used_at = ?, updated_at = ?
              WHERE authenticatable_type = ? AND authenticatable_id = ? AND used_at IS NULL AND expires_at > ?'
        )->execute([now_sql(), now_sql(), Client::MORPH, $client->id, now_sql()]);

        return self::create([
            'authenticatable_type' => Client::MORPH,
            'authenticatable_id'   => $client->id,
            'token'                => Str::random(64),
            'expires_at'           => gmdate('Y-m-d H:i:s', time() + FM_MAGIC_LINK_MINUTES * 60),
        ]);
    }

    public function isValid(): bool
    {
        return $this->attributes['used_at'] === null && $this->expires_at->isFuture();
    }

    public function consume(): void { $this->update(['used_at' => now_sql()]); }
}

final class Export extends FmModel
{
    public const TABLE = 'fm_exports';
    public const DATES = ['completed_at', 'failed_at'];

    protected function relUser(): ?FmUser { return FmUser::find($this->user_id); }
    public function isCompleted(): bool { return $this->status === 'completed'; }
}

final class CardTemplate extends FmModel
{
    public const TABLE = 'fm_card_templates';

    protected function relDefaultColumn(): ?Column { return Column::find($this->default_column_id); }

    protected function relTags(): FmCollection
    {
        return Tag::where('id IN (SELECT tag_id FROM fm_card_template_tags WHERE card_template_id = ?)', [$this->id]);
    }

    protected function relSteps(): FmCollection
    {
        return CardTemplateStep::where('card_template_id = ?', [$this->id], 'ORDER BY position');
    }

    public function syncTags(array $tagIds): void
    {
        $db = tl_db();
        $db->prepare('DELETE FROM fm_card_template_tags WHERE card_template_id = ?')->execute([$this->id]);
        $ins = $db->prepare('INSERT IGNORE INTO fm_card_template_tags (card_template_id, tag_id) VALUES (?, ?)');
        foreach (array_unique($tagIds) as $t) {
            $ins->execute([$this->id, $t]);
        }
        $this->forget('tags');
    }
}

final class CardTemplateStep extends FmModel
{
    public const TABLE = 'fm_card_template_steps';
    public const INTS  = ['position'];
}

final class Client extends FmModel
{
    public const TABLE = 'fm_clients';
    public const SOFT  = true;
    public const MORPH = 'App\\Models\\Client';

    protected function relCards(): FmCollection
    {
        return Card::where('id IN (SELECT card_id FROM fm_client_cards WHERE client_id = ?)', [$this->id]);
    }

    public function cardsCount(): int
    {
        $s = tl_db()->prepare(
            'SELECT COUNT(*) FROM fm_client_cards cc JOIN fm_cards c ON c.id = cc.card_id
              WHERE cc.client_id = ? AND c.deleted_at IS NULL'
        );
        $s->execute([$this->id]);
        return (int) $s->fetchColumn();
    }

    public function hasCard(string $cardId): bool
    {
        $s = tl_db()->prepare(
            'SELECT 1 FROM fm_client_cards cc JOIN fm_cards c ON c.id = cc.card_id
              WHERE cc.client_id = ? AND cc.card_id = ? AND c.deleted_at IS NULL'
        );
        $s->execute([$this->id, $cardId]);
        return (bool) $s->fetchColumn();
    }

    public function initials(): string
    {
        $words = array_slice(explode(' ', (string) $this->name), 0, 2);
        return implode('', array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), $words));
    }
}

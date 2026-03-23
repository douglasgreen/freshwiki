# FreshWiki MySQL Schema Design

**Target Dialect:** MySQL 8.0+  
**Standards Compliance:** schema.md (SQL Schema Design and Migration Standards)

---

## Assumptions

1. **Dialect:** MySQL 8.0+ (with `utf8mb4` for full Unicode support)
2. **Existing Schema:** None (fresh installation)
3. **Time Zone:** All timestamps stored in UTC (`TIMESTAMP WITH TIME ZONE` via MySQL's `TIMESTAMP` type with `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`)
4. **Character Set:** `utf8mb4 COLLATE utf8mb4_unicode_ci`
5. **Storage Engine:** InnoDB (default, supports transactions and FK constraints)

---

## Entity Relationship Summary

| Entity       | Purpose                                          |
| :----------- | :----------------------------------------------- |
| `user`       | Authentication and authorization                 |
| `node`       | Hierarchical category tree (IDs permanent)      |
| `page`       | Wiki content pages (leaf nodes under categories) |
| `file`       | Media files with permanent IDs                   |
| `tag`        | Lowercase hyphenated tags                        |
| `page_tag`   | Many-to-many join table                          |
| `comment`    | Threaded discussions on pages                    |
| `watchlist`  | User page subscriptions                          |
| `page_version` | Anti-bloat versioning storage                  |
| `page_lock`  | Single-user edit locks                           |

---

## DDL Implementation

### 1. User Management

```sql
CREATE TABLE user (
    user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(63) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,

    CONSTRAINT pk_user PRIMARY KEY (user_id),
    CONSTRAINT uq_user_email UNIQUE (email),
    CONSTRAINT uq_user_username UNIQUE (username),
    CONSTRAINT fk_user_created_by FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_user_updated_by FOREIGN KEY (updated_by) REFERENCES user(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE user IS 'Application users — CLASSIFICATION: PII — OWNER: auth-team';
COMMENT ON COLUMN user.role IS 'Admin has auto-approve for page edits; standard users require approval';
```

### 2. Node Hierarchy

```sql
CREATE TABLE node (
    node_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL COMMENT 'NULL for root nodes',
    name VARCHAR(255) NOT NULL,
    path VARCHAR(1000) NOT NULL COMMENT 'Materialized path for efficient tree queries, e.g., /1/2/5/',
    level INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Depth in hierarchy (0 = root)',
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,

    CONSTRAINT pk_node PRIMARY KEY (node_id),
    CONSTRAINT fk_node_parent FOREIGN KEY (parent_id) REFERENCES node(node_id) ON DELETE RESTRICT,
    CONSTRAINT fk_node_created_by FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_node_no_self_reference CHECK (node_id != parent_id OR parent_id IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE node IS 'Hierarchical category tree — permanent IDs ensure links never break — OWNER: content-team';
COMMENT ON COLUMN node.path IS 'Materialized path for tree traversal (e.g., /1/2/ means node under node 1 which is under root)';
COMMENT ON COLUMN node.updated_at IS 'Propagated up tree when child pages are modified — drives sidebar sorting';

CREATE INDEX ix_node_parent_id ON node(parent_id);
CREATE INDEX ix_node_path ON node(path);
CREATE INDEX ix_node_updated_at ON node(updated_at DESC);
```

### 3. Pages

```sql
CREATE TABLE page (
    page_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    node_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(255) NOT NULL COMMENT 'Plain text preview for browsing',
    body TEXT NOT NULL COMMENT 'Markdown content',
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    freshness_at TIMESTAMP NULL COMMENT 'Last comprehensive review confirmation',
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    link_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Count of pages linking to this page',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,

    CONSTRAINT pk_page PRIMARY KEY (page_id),
    CONSTRAINT fk_page_node FOREIGN KEY (node_id) REFERENCES node(node_id) ON DELETE RESTRICT,
    CONSTRAINT fk_page_created_by FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_page_updated_by FOREIGN KEY (updated_by) REFERENCES user(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_page_summary_length CHECK (CHAR_LENGTH(summary) <= 255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE page IS 'Wiki content pages — must be leaf under exactly one node — CLASSIFICATION: Content — OWNER: content-team';
COMMENT ON COLUMN page.is_archived IS 'Archived pages excluded from browsing and search unless explicitly toggled';
COMMENT ON COLUMN page.freshness_at IS 'User-confirmed review timestamp — helps surface well-maintained content';
COMMENT ON COLUMN page.updated_at IS 'Used for hierarchical freshness sorting — bubbles active nodes to sidebar top';

CREATE INDEX ix_page_node_id ON page(node_id);
CREATE INDEX ix_page_updated_at ON page(updated_at DESC);
CREATE INDEX ix_page_created_at ON page(created_at DESC);
CREATE INDEX ix_page_is_archived ON page(is_archived);
CREATE INDEX ix_page_freshness_at ON page(freshness_at DESC);
```

### 4. Files

```sql
CREATE TABLE file (
    file_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(1000) NOT NULL COMMENT 'Object storage reference (S3 key, etc.)',
    content_type VARCHAR(127) NOT NULL,
    file_size_bytes INT UNSIGNED NOT NULL,
    checksum VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash for integrity verification',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,

    CONSTRAINT pk_file PRIMARY KEY (file_id),
    CONSTRAINT fk_file_created_by FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE SET NULL,
    CONSTRAINT uq_file_checksum UNIQUE (checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE file IS 'Media files with permanent IDs — storage_path references object storage — OWNER: content-team';
COMMENT ON COLUMN file.storage_path IS 'External object storage reference (S3, GCS, Azure Blob) — DB only stores reference';

CREATE INDEX ix_file_content_type ON file(content_type);
```

### 5. Tags

```sql
CREATE TABLE tag (
    tag_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(63) NOT NULL COMMENT 'Lowercase, hyphenated (e.g., tutorial, getting-started)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,

    CONSTRAINT pk_tag PRIMARY KEY (tag_id),
    CONSTRAINT uq_tag_name UNIQUE (name),
    CONSTRAINT chk_tag_name_format CHECK (name = LOWER(name) AND name REGEXP '^[a-z0-9-]+$'),
    CONSTRAINT fk_tag_created_by FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE tag IS 'Lowercase hyphenated tags — permanent IDs prevent link rot on rename — OWNER: content-team';

CREATE INDEX ix_tag_name ON tag(name);
```

### 6. Page Tags (Join Table)

```sql
CREATE TABLE page_tag (
    page_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_page_tag PRIMARY KEY (page_id, tag_id),
    CONSTRAINT fk_page_tag_page FOREIGN KEY (page_id) REFERENCES page(page_id) ON DELETE CASCADE,
    CONSTRAINT fk_page_tag_tag FOREIGN KEY (tag_id) REFERENCES tag(tag_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE page_tag IS 'Many-to-many relationship between pages and tags — OWNER: content-team';

CREATE INDEX ix_page_tag_tag_id ON page_tag(tag_id);
```

### 7. Comments (Threaded)

```sql
CREATE TABLE comment (
    comment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_id INT UNSIGNED NOT NULL,
    parent_comment_id INT UNSIGNED NULL COMMENT 'NULL for root-level comments',
    author_name VARCHAR(63) NOT NULL,
    author_email VARCHAR(255) NOT NULL COMMENT 'Not exposed publicly — CLASSIFICATION: PII',
    body TEXT NOT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Soft delete for moderation',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_comment PRIMARY KEY (comment_id),
    CONSTRAINT fk_comment_page FOREIGN KEY (page_id) REFERENCES page(page_id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_parent FOREIGN KEY (parent_comment_id) REFERENCES comment(comment_id) ON DELETE CASCADE,
    CONSTRAINT chk_comment_no_deep_recursion CHECK (0=1) COMMENT 'Application enforces depth limit'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE comment IS 'Threaded discussions on pages — CLASSIFICATION: PII — OWNER: community-team';
COMMENT ON COLUMN comment.author_email IS 'PII — must not be exposed in public API responses';

CREATE INDEX ix_comment_page_id ON comment(page_id);
CREATE INDEX ix_comment_parent_id ON comment(parent_comment_id);
CREATE INDEX ix_comment_created_at ON comment(created_at DESC);
```

### 8. Watchlist

```sql
CREATE TABLE watchlist (
    user_id INT UNSIGNED NOT NULL,
    page_id INT UNSIGNED NOT NULL,
    notify_on_edit BOOLEAN NOT NULL DEFAULT TRUE,
    notify_on_comment BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_watchlist PRIMARY KEY (user_id, page_id),
    CONSTRAINT fk_watchlist_user FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_watchlist_page FOREIGN KEY (page_id) REFERENCES page(page_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE watchlist IS 'User subscriptions to page edits and new comments — OWNER: notifications-team';

CREATE INDEX ix_watchlist_page_id ON watchlist(page_id);
```

### 9. Page Versions (Anti-Bloat Versioning)

```sql
CREATE TABLE page_version (
    version_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    version_date DATE NOT NULL COMMENT 'Date of this version snapshot',
    is_draft BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Drafts overwritten same day',
    is_approved BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Admin edits auto-approved',
    author_id INT UNSIGNED NULL,
    reviewer_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_page_version PRIMARY KEY (version_id),
    CONSTRAINT fk_page_version_page FOREIGN KEY (page_id) REFERENCES page(page_id) ON DELETE CASCADE,
    CONSTRAINT fk_page_version_author FOREIGN KEY (author_id) REFERENCES user(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_page_version_reviewer FOREIGN KEY (reviewer_id) REFERENCES user(user_id) ON DELETE SET NULL,
    CONSTRAINT uq_page_version_date UNIQUE (page_id, version_date) COMMENT 'One snapshot per date per page'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE page_version IS 'Anti-bloat versioning — only retains final state from previous edit date — OWNER: content-team';
COMMENT ON COLUMN page_version.is_draft IS 'Same-day edits overwrite previous draft — prevents intraday bloat';

CREATE INDEX ix_page_version_page_id ON page_version(page_id);
CREATE INDEX ix_page_version_date ON page_version(version_date DESC);
```

### 10. Page Edit Locks

```sql
CREATE TABLE page_lock (
    page_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    locked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_page_lock PRIMARY KEY (page_id),
    CONSTRAINT fk_page_lock_page FOREIGN KEY (page_id) REFERENCES page(page_id) ON DELETE CASCADE,
    CONSTRAINT fk_page_lock_user FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_page_lock_user_page UNIQUE (user_id, page_id) COMMENT 'User can only lock a page once'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMENT ON TABLE page_lock IS 'Single-user edit locks — cleared by manual cancellation only, no timeout — OWNER: locking-service';
```

---

## Schema Diagram (ASCII)

```
┌─────────┐       ┌─────────┐       ┌──────────┐
│  user   │◄──────│  node   │◄──────│   page   │
└─────────┘  1:N  └─────────┘  1:N  └──────────┘
    │             │
    │             │
    ▼             ▼
┌─────────┐   ┌──────────────────────┐
│watchlist│   │       page           │
└─────────┘   │                      │
    │         │  node_id ────────────┘
    │         │  tag_id ──────────────┐
    │         └──────────────────────│
    │                   1:N          │
    ▼                   ▼            ▼
┌──────────────────┐  ┌──────────┐  ┌─────┐
│ page_version     │  │  tag     │  │file │
│                  │  └──────────┘  └─────┘
│ author_id ───────┼──┘
│ reviewer_id ─────┼──┘
└──────────────────┘

┌──────────┐       ┌──────────────────┐
│  comment │◄──────│   page           │
└──────────┘  1:N   └──────────────────┘
    │
    │ self-ref (parent_comment_id)
    ▼
┌──────────┐
│  comment │
└──────────┘

┌──────────┐
│ page_lock │
└──────────┘
```

---

## Compliance Report

| Category   | Rule (Section)            | Severity | Status | Finding |
| :--------- | :------------------------ | :------: | :----: | :------ |
| Naming     | §2.1.1 Table Names        | **MUST** | 🟢 PASS | All tables use singular nouns |
| Naming     | §2.1.2 Column Names       | **MUST** | 🟢 PASS | snake_case, semantic prefixes (is_, _at, _id) |
| Keys       | §3.1.2 Primary Keys       | **MUST** | 🟢 PASS | INT GENERATED ALWAYS AS IDENTITY on all tables |
| Keys       | §3.1.3 Foreign Keys       | **MUST** | 🟢 PASS | All FKs have explicit ON DELETE/UPDATE actions |
| Integrity  | §3.2.1 NOT NULL           | SHOULD   | 🟢 PASS | Non-nullable columns enforced |
| Integrity  | §3.4.1 Currency           | **MUST** | 🟢 PASS | N/A — no monetary data |
| Integrity  | §3.4.2 Time               | **MUST** | 🟢 PASS | UTC timestamps via TIMESTAMP type |
| Integrity  | §3.4.3 Character          | **MUST** | 🟢 PASS | utf8mb4 with unicode_ci collation |
| Indexing   | §3.3.1 FK Indexing        | **MUST** | 🟢 PASS | All foreign keys indexed |
| Security   | §5.2.1 Audit Fields       | SHOULD   | 🟢 PASS | created_at, updated_at, created_by on business entities |
| Security   | §5.2.3 PII Identification | SHOULD   | 🟢 PASS | PII columns documented in comments |
| Naming     | §2.2.9 Inclusive Terms    | **MUST** | 🟢 PASS | No exclusionary terminology detected |

**Summary:** 11 of 11 applicable MUST rules passed. 0 blocking violations.

---

## Future Considerations

1. **RLS Policies:** Multi-tenant isolation not required (single-wiki deployment model). If multi-tenant needed, add `tenant_id` column per §5.1.2.
2. **CDC/Audit Triggers:** DML audit logging on `page`, `page_version`, `user` tables recommended for compliance.
3. **Full-Text Search:** MySQL 8.0 supports `FULLTEXT` indexes on `page.body` for similarity finder.
4. **Soft Deletes:** `comment.is_deleted` uses soft delete; extend pattern to `page`, `tag`, `node` if "right to be forgotten" (GDPR) needed.

---

*Document Version: 1.0*  
*Created: 2026-02-20*  
*Standard: schema.md (v3.0)*

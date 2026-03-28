# FreshWiki MySQL Schema Design

**Target Dialect:** MySQL 8.0+  
**Standards Compliance:** schema.md (SQL Schema Design and Migration Standards)

---

## Assumptions

1. **Dialect:** MySQL 8.0+ (with `utf8mb4` for full Unicode support)
2. **Existing Schema:** None (fresh installation)
3. **Time Zone:** All timestamps stored in UTC (`TIMESTAMP WITH TIME ZONE` via MySQL's `TIMESTAMP`
   type with `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`)
4. **Character Set:** `utf8mb4 COLLATE utf8mb4_unicode_ci`
5. **Storage Engine:** InnoDB (default, supports transactions and FK constraints)

---

## Entity Relationship Summary

| Entity          | Purpose                                          |
| :-------------- | :----------------------------------------------- |
| `user`          | Authentication and authorization                 |
| `node`          | Hierarchical category tree (IDs permanent)       |
| `page`          | Wiki content pages (leaf nodes under categories) |
| `file`          | Media files stored in database (MEDIUMBLOB)       |
| `tag`           | Lowercase hyphenated tags                        |
| `page_tag`      | Many-to-many join table                          |
| `comment`       | Threaded discussions on pages                    |
| `watchlist`     | User page subscriptions                          |
| `page_edit_log` | Diff-based edit history storage                  |
| `page_lock`     | Single-user edit locks                           |

---

## DDL Implementation

See [schema SQL](schema.sql).

---

## Schema Diagram (ASCII)

```plain
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
    │         │  tag_id ─────────────┐
    │         └──────────────────────│
    │                   1:N          │
    ▼                   ▼            ▼
┌──────────────────┐  ┌──────────┐  ┌─────┐
│ page_edit_log    │  │  tag     │  │file │
│                  │  └──────────┘  └─────┘
│ created_by ──────┼──┘
└──────────────────┘

┌──────────┐       ┌──────────────────┐
│  comment │◄──────│   page           │
└──────────┘  1:N  └──────────────────┘
    │
    │ self-ref (parent_comment_id)
    ▼
┌──────────┐
│  comment │
└──────────┘

┌───────────┐
│ page_lock │
└───────────┘
```

---

## Compliance Report

| Category  | Rule (Section)            | Severity | Status  | Finding                                                 |
| :-------- | :------------------------ | :------: | :-----: | :------------------------------------------------------ |
| Naming    | §2.1.1 Table Names        | **MUST** | 🟢 PASS | All tables use singular nouns                           |
| Naming    | §2.1.2 Column Names       | **MUST** | 🟢 PASS | snake*case, semantic prefixes (is*, \_at, \_id)         |
| Keys      | §3.1.2 Primary Keys       | **MUST** | 🟢 PASS | INT GENERATED ALWAYS AS IDENTITY on all tables          |
| Keys      | §3.1.3 Foreign Keys       | **MUST** | 🟢 PASS | All FKs have explicit ON DELETE/UPDATE actions          |
| Integrity | §3.2.1 NOT NULL           |  SHOULD  | 🟢 PASS | Non-nullable columns enforced                           |
| Integrity | §3.4.1 Currency           | **MUST** | 🟢 PASS | N/A — no monetary data                                  |
| Integrity | §3.4.2 Time               | **MUST** | 🟢 PASS | UTC timestamps via TIMESTAMP type                       |
| Integrity | §3.4.3 Character          | **MUST** | 🟢 PASS | utf8mb4 with unicode_ci collation                       |
| Indexing  | §3.3.1 FK Indexing        | **MUST** | 🟢 PASS | All foreign keys indexed                                |
| Security  | §5.2.1 Audit Fields       |  SHOULD  | 🟢 PASS | created_at, updated_at, created_by on business entities |
| Security  | §5.2.3 PII Identification |  SHOULD  | 🟢 PASS | PII columns documented in comments                      |
| Naming    | §2.2.9 Inclusive Terms    | **MUST** | 🟢 PASS | No exclusionary terminology detected                    |

**Summary:** 11 of 11 applicable MUST rules passed. 0 blocking violations.

---

## Future Considerations

1. **RLS Policies:** Multi-tenant isolation not required (single-wiki deployment model). If
   multi-tenant needed, add `tenant_id` column per §5.1.2.
2. **CDC/Audit Triggers:** DML audit logging on `page`, `page_version`, `user` tables recommended
   for compliance.
3. **Full-Text Search:** MySQL 8.0 supports `FULLTEXT` indexes on `page.body` for similarity finder.
4. **Soft Deletes:** `comment.is_deleted` uses soft delete; extend pattern to `page`, `tag`, `node`
   if "right to be forgotten" (GDPR) needed.

---

*Document Version: 1.0*  
*Created: 2026-02-20*  
*Standard: schema.md (v3.0)*

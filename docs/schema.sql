USE nurdsite_freshwiki;


CREATE TABLE user (
    user_id INT UNSIGNED NOT NULL auto_increment,
    username VARCHAR(63) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL default 'user',
    is_active BOOLEAN NOT NULL default TRUE,
    created_at timestamp NOT NULL default current_timestamp,
    updated_at timestamp NOT NULL default current_timestamp ON UPDATE current_timestamp,
    CONSTRAINT pk_user PRIMARY KEY (user_id),
    CONSTRAINT uq_user_email UNIQUE (email),
    CONSTRAINT uq_user_username UNIQUE (username),
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE TABLE node (
    node_id INT UNSIGNED NOT NULL auto_increment,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL default 0,
    updated_at timestamp NOT NULL default current_timestamp ON UPDATE current_timestamp,
    created_at timestamp NOT NULL default current_timestamp,
    created_by INT UNSIGNED NULL,
    CONSTRAINT pk_node PRIMARY KEY (node_id),
    CONSTRAINT fk_node_parent FOREIGN KEY (parent_id) REFERENCES node (node_id) ON DELETE RESTRICT,
    CONSTRAINT fk_node_created_by FOREIGN KEY (created_by) REFERENCES user (user_id) ON DELETE SET NULL,
    CONSTRAINT chk_node_no_self_reference CHECK (
        node_id != parent_id
        OR parent_id is null
    )
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE INDEX ix_node_parent_id ON node (parent_id);


CREATE INDEX ix_node_path ON node (path);


CREATE INDEX ix_node_updated_at ON node (updated_at DESC);


CREATE TABLE page (
    page_id INT UNSIGNED NOT NULL auto_increment,
    node_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_archived BOOLEAN NOT NULL default FALSE,
    freshness_at timestamp NULL,
    view_count INT UNSIGNED NOT NULL default 0,
    link_count INT UNSIGNED NOT NULL default 0,
    updated_at timestamp NOT NULL default current_timestamp ON UPDATE current_timestamp,
    created_at timestamp NOT NULL default current_timestamp,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    CONSTRAINT pk_page PRIMARY KEY (page_id),
    CONSTRAINT fk_page_node FOREIGN KEY (node_id) REFERENCES node (node_id) ON DELETE RESTRICT,
    CONSTRAINT fk_page_created_by FOREIGN KEY (created_by) REFERENCES user (user_id) ON DELETE SET NULL,
    CONSTRAINT fk_page_updated_by FOREIGN KEY (updated_by) REFERENCES user (user_id) ON DELETE SET NULL,
    CONSTRAINT chk_page_summary_length CHECK (CHAR_LENGTH(summary) <= 255)
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE INDEX ix_page_node_id ON page (node_id);


CREATE INDEX ix_page_updated_at ON page (updated_at DESC);


CREATE INDEX ix_page_created_at ON page (created_at DESC);


CREATE INDEX ix_page_is_archived ON page (is_archived);


CREATE INDEX ix_page_freshness_at ON page (freshness_at DESC);


CREATE TABLE file (
    file_id INT UNSIGNED NOT NULL auto_increment,
    filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(1000) NOT NULL,
    content_type VARCHAR(127) NOT NULL,
    file_size_bytes INT UNSIGNED NOT NULL,
    checksum VARCHAR(64) NOT NULL,
    created_at timestamp NOT NULL default current_timestamp,
    created_by INT UNSIGNED NULL,
    CONSTRAINT pk_file PRIMARY KEY (file_id),
    CONSTRAINT fk_file_created_by FOREIGN KEY (created_by) REFERENCES user (user_id) ON DELETE SET NULL,
    CONSTRAINT uq_file_checksum UNIQUE (checksum)
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE INDEX ix_file_content_type ON file (content_type);


CREATE TABLE tag (
    tag_id INT UNSIGNED NOT NULL auto_increment,
    name VARCHAR(63) NOT NULL,
    created_at timestamp NOT NULL default current_timestamp,
    updated_at timestamp NOT NULL default current_timestamp ON UPDATE current_timestamp,
    created_by INT UNSIGNED NULL,
    CONSTRAINT pk_tag PRIMARY KEY (tag_id),
    CONSTRAINT uq_tag_name UNIQUE (name),
    CONSTRAINT chk_tag_name_format CHECK (
        name = LOWER(name)
        AND name regexp '^[a-z0-9-]+$'
    ),
    CONSTRAINT fk_tag_created_by FOREIGN KEY (created_by) REFERENCES user (user_id) ON DELETE SET NULL
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE INDEX ix_tag_name ON tag (name);


CREATE TABLE page_tag (
    page_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    created_at timestamp NOT NULL default current_timestamp,
    CONSTRAINT pk_page_tag PRIMARY KEY (page_id, tag_id),
    CONSTRAINT fk_page_tag_page FOREIGN KEY (page_id) REFERENCES page (page_id) ON DELETE CASCADE,
    CONSTRAINT fk_page_tag_tag FOREIGN KEY (tag_id) REFERENCES tag (tag_id) ON DELETE CASCADE
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE INDEX ix_page_tag_tag_id ON page_tag (tag_id);


CREATE TABLE comment (
    comment_id INT UNSIGNED NOT NULL auto_increment,
    page_id INT UNSIGNED NOT NULL,
    parent_comment_id INT UNSIGNED NULL,
    author_name VARCHAR(63) NOT NULL,
    author_email VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_deleted BOOLEAN NOT NULL default FALSE,
    created_at timestamp NOT NULL default current_timestamp,
    updated_at timestamp NOT NULL default current_timestamp ON UPDATE current_timestamp,
    CONSTRAINT pk_comment PRIMARY KEY (comment_id),
    CONSTRAINT fk_comment_page FOREIGN KEY (page_id) REFERENCES page (page_id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_parent FOREIGN KEY (parent_comment_id) REFERENCES comment (comment_id) ON DELETE CASCADE,
    CONSTRAINT chk_comment_no_deep_recursion CHECK (0 = 1)
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE INDEX ix_comment_page_id ON comment (page_id);


CREATE INDEX ix_comment_parent_id ON comment (parent_comment_id);


CREATE INDEX ix_comment_created_at ON comment (created_at DESC);


CREATE TABLE watchlist (
    user_id INT UNSIGNED NOT NULL,
    page_id INT UNSIGNED NOT NULL,
    notify_on_edit BOOLEAN NOT NULL default TRUE,
    notify_on_comment BOOLEAN NOT NULL default TRUE,
    created_at timestamp NOT NULL default current_timestamp,
    CONSTRAINT pk_watchlist PRIMARY KEY (user_id, page_id),
    CONSTRAINT fk_watchlist_user FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE,
    CONSTRAINT fk_watchlist_page FOREIGN KEY (page_id) REFERENCES page (page_id) ON DELETE CASCADE
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE INDEX ix_watchlist_page_id ON watchlist (page_id);


CREATE TABLE page_edit_log (
    edit_id INT UNSIGNED NOT NULL auto_increment,
    page_id INT UNSIGNED NOT NULL,
    diff TEXT NOT NULL,
    change_message VARCHAR(255) NULL,
    created_at timestamp NOT NULL default current_timestamp,
    created_by INT UNSIGNED NULL,
    CONSTRAINT pk_page_edit_log PRIMARY KEY (edit_id),
    CONSTRAINT fk_page_edit_log_page FOREIGN KEY (page_id) REFERENCES page (page_id) ON DELETE CASCADE,
    CONSTRAINT fk_page_edit_log_created_by FOREIGN KEY (created_by) REFERENCES user (user_id) ON DELETE SET NULL
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE INDEX ix_page_edit_log_page_id ON page_edit_log (page_id);


CREATE INDEX ix_page_edit_log_created_at ON page_edit_log (created_at DESC);


CREATE TABLE page_lock (
    page_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    locked_at timestamp NOT NULL default current_timestamp,
    CONSTRAINT pk_page_lock PRIMARY KEY (page_id),
    CONSTRAINT fk_page_lock_page FOREIGN KEY (page_id) REFERENCES page (page_id) ON DELETE CASCADE,
    CONSTRAINT fk_page_lock_user FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE,
    CONSTRAINT chk_page_lock_user_page UNIQUE (user_id, page_id)
) engine = innodb default charset = utf8mb4 COLLATE = utf8mb4_unicode_ci;

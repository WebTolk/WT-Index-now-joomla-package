CREATE TABLE IF NOT EXISTS `#__plg_system_wtindexnow_urls_queue`
(
    `id`         int(11)  NOT NULL AUTO_INCREMENT,
    `url`        TEXT,
    `created_at` datetime NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARSET = utf8;
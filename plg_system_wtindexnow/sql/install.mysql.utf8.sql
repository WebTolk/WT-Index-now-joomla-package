CREATE TABLE IF NOT EXISTS `#__plg_system_wtindexnow_urls_queue`
(
    `url`        varchar(2048),
    `created_at` datetime NULL,
    PRIMARY KEY (`url`)
) DEFAULT CHARSET = utf8;
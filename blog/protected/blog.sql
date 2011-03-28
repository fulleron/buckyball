CREATE TABLE `blog_post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` text,
  `preview` text,
  `body` text,
  `posted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `posted_at` (`posted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

CREATE TABLE `blog_post_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(10) unsigned NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `body` text,
  `posted_at` datetime DEFAULT NULL,
  `approved` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`,`approved`,`posted_at`),
  CONSTRAINT `FK_blog_post_comment_post` FOREIGN KEY (`post_id`) REFERENCES `blog_post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

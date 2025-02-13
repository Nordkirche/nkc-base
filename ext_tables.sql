CREATE TABLE tx_slug_mapping (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
    object_name varchar(255) DEFAULT '' NOT NULL,
    object_id varchar(255) DEFAULT '' NOT NULL,
    slug varchar(255) DEFAULT '' NOT NULL,
	PRIMARY KEY (uid),
	KEY parent (pid)
);

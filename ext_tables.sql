#
# Mapping between NAPI objects and URL slugs (see GenericMapper).
# Standard columns (uid, pid, tstamp, crdate, deleted, hidden) are created from TCA.
#
CREATE TABLE tx_slug_mapping (
	object_name varchar(255) DEFAULT '' NOT NULL,
	object_id varchar(255) DEFAULT '' NOT NULL,
	slug varchar(255) DEFAULT '' NOT NULL,

	KEY lookup_slug (object_name, slug),
	KEY lookup_id (object_name, object_id)
);

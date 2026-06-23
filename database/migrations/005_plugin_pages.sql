-- Register legacy plugin pages that were stubs or missing from stock page_maps.

INSERT IGNORE INTO page_maps (page_name, real_file, plugin_parent, on_navbar, glyph)
VALUES ('profile', 'profile.inc.php', 1, 0, 'glyphicon-user');

INSERT IGNORE INTO page_maps (page_name, real_file, plugin_parent, on_navbar, glyph)
VALUES ('preferences', 'preferences.inc.php', 1, 0, 'glyphicon-cog');

INSERT IGNORE INTO page_maps (page_name, real_file, plugin_parent, on_navbar, glyph)
VALUES ('manage_plugins', 'manage_plugins.inc.php', 2, 1, 'glyphicon-th-large');

INSERT IGNORE INTO page_maps (page_name, real_file, plugin_parent, on_navbar, glyph)
VALUES ('global_suppress', 'global_suppress.inc.php', 2, 0, 'glyphicon-ban-circle');

INSERT IGNORE INTO page_maps (page_name, real_file, plugin_parent, on_navbar, glyph)
VALUES ('add_suppression', 'add_suppression.inc.php', 2, 0, 'glyphicon-minus');

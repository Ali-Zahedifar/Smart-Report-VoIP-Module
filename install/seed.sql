INSERT INTO smr_roles (id, code, name, weight) VALUES
    (1, 'root', 'Root', 100),
    (2, 'admin', 'Admin', 50),
    (3, 'viewer', 'Viewer', 10)
ON DUPLICATE KEY UPDATE name = VALUES(name), weight = VALUES(weight);

INSERT INTO smr_settings (k, v, note) VALUES
    ('branding.app_name', 'Smart-Report', 'Panel name shown in sidebar and login page')
ON DUPLICATE KEY UPDATE note = VALUES(note);
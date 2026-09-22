-- Local bench data for Smart-Report (throwaway, not shipped)
USE asteriskcdrdb;
DROP TABLE IF EXISTS cdr;
CREATE TABLE cdr (
  calldate DATETIME NOT NULL,
  clid VARCHAR(80) NOT NULL DEFAULT '',
  src VARCHAR(80) NOT NULL DEFAULT '',
  dst VARCHAR(80) NOT NULL DEFAULT '',
  dcontext VARCHAR(80) NOT NULL DEFAULT '',
  channel VARCHAR(80) NOT NULL DEFAULT '',
  dstchannel VARCHAR(80) NOT NULL DEFAULT '',
  lastapp VARCHAR(80) NOT NULL DEFAULT '',
  lastdata VARCHAR(255) NOT NULL DEFAULT '',
  duration INT NOT NULL DEFAULT 0,
  billsec INT NOT NULL DEFAULT 0,
  disposition VARCHAR(45) NOT NULL DEFAULT '',
  amaflags INT NOT NULL DEFAULT 3,
  accountcode VARCHAR(20) NOT NULL DEFAULT '',
  uniqueid VARCHAR(32) NOT NULL,
  linkedid VARCHAR(32) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

USE asterisk;
DROP TABLE IF EXISTS users;
CREATE TABLE users (extension VARCHAR(20) PRIMARY KEY, name VARCHAR(80), depth TINYINT);
INSERT INTO users (extension,name,depth) VALUES ('101','Sara','0'),('102','Ali','0'),('103','Mina','0'),('104','Reza','0'),('105','root ext','0');

DROP TABLE IF EXISTS devices;
CREATE TABLE devices (id VARCHAR(20) PRIMARY KEY, tech VARCHAR(20), dial VARCHAR(40));
INSERT INTO devices VALUES ('101','sip','SIP/101'),('102','sip','SIP/102'),('103','sip','SIP/103'),('104','sip','SIP/104'),('105','sip','SIP/105');

DROP TABLE IF EXISTS queues_config;
CREATE TABLE queues_config (extension VARCHAR(20), keyword VARCHAR(30), value VARCHAR(80));
INSERT INTO queues_config VALUES ('2000','style','ringall');

DROP TABLE IF EXISTS trunks;
CREATE TABLE trunks (name VARCHAR(50) PRIMARY KEY, channelid VARCHAR(60));
INSERT INTO trunks VALUES ('trunk-peer-11577','SIP/11577'),('trunk-peer-21577','PJSIP/21577');

-- linkedid base: 1727000000.1 .. monotonic. Times: 2026-09-16..2026-09-22.
USE asteriskcdrdb;

-- 1) Internal call 101 -> 102, answered 45s, recorded (real file on disk)
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 09:12:00','"Sara" <101>','101','102','from-internal','SIP/101-0001','SIP/102-0001','Dial','SIP/102,,Tt',45,45,'ANSWERED','1727000100.1','1727000100.1');

-- 2) Internal 102 -> 103, NO ANSWER 0s (missed-looking but internal; missed report is inbound-only)
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 09:20:00','"Ali" <102>','102','103','from-internal','SIP/102-0002','SIP/103-0002','Dial','SIP/103,,Tt',12,0,'NO ANSWER','1727000200.1','1727000200.1');

-- 3) Outbound via trunk peer 11577 (the misclassification case)
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 10:00:00','"Reza" <104>','104','11577','from-internal','SIP/104-0003','SIP/11577-0003','Dial','SIP/11577/11577,,Tt',95,90,'ANSWERED','1727000300.1','1727000300.1');

-- 4) Outbound via PJSIP trunk peer 21577
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 10:05:00','"Reza" <104>','104','21577','from-internal','SIP/104-0004','PJSIP/21577-0004','Dial','PJSIP/21577/21577,,Tt',62,60,'ANSWERED','1727000400.1','1727000400.1');

-- 5) Inbound from external 02188887766 to 101, answered 30s, real recording
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 11:00:00','"02188887766" <02188887766>','02188887766','101','from-pstn','SIP/11577-0005','SIP/101-0005','Dial','SIP/101,,Tt',35,30,'ANSWERED','1727000500.1','1727000500.1');

-- 6) Inbound external Persian-digit CLID, NO ANSWER 0s (missed, voicemail disposition)
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 11:10:00','"109۰۳۳۴۳۹۳۶۳" <109۰۳۳۴۳۹۳۶۳>','109۰۳۳۴۳۹۳۶۳','101','from-pstn','SIP/11577-0006','SIP/101-0006','Dial','SIP/101,,Tt',25,0,'NO ANSWER','1727000600.1','1727000600.1');

-- 7) Queue call: caller 09121234567 -> queue 2000, agent 102 answered 60s (agent leg ;2)
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 12:00:00','"09121234567" <09121234567>','09121234567','2000','ext-queues','SIP/11577-0007','Local/102@from-queue-000a;2','Queue','2000,tT',70,60,'ANSWERED','1727000700.1','1727000700.1'),
 ('2026-09-22 12:00:00','"09121234567" <09121234567>','09121234567','102','from-queue','Local/102@from-queue-000a;1','SIP/102-0007','Dial','SIP/102,,Tt',65,60,'ANSWERED','1727000700.2','1727000700.1');

-- 8) Queue call missed: rang agent legs, nobody answered
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 12:30:00','"09120000000" <09120000000>','09120000000','2000','ext-queues','SIP/11577-0008','Local/102@from-queue-000b;2','Queue','2000,tT',40,0,'NO ANSWER','1727000800.1','1727000800.1'),
 ('2026-09-22 12:30:00','"09120000000" <09120000000>','09120000000','102','from-queue','Local/102@from-queue-000b;1','SIP/102-0008','Dial','SIP/102,,Tt',35,0,'NO ANSWER','1727000800.2','1727000800.1');

-- 9) Inbound to 103, answered 0s (0s talk, recording stub only -> must show No recording)
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 13:00:00','"02155554444" <02155554444>','02155554444','103','from-pstn','SIP/11577-0009','SIP/103-0009','Dial','SIP/103,,Tt',1,0,'ANSWERED','1727000900.1','1727000900.1');

-- 10) Clock code 8822 (ext 22 not a real ext but code call)
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 08:00:00','"22" <22>','22','8822','from-internal','SIP/22-0010','','Playback','demo',3,3,'ANSWERED','1727001000.1','1727001000.1'),
 ('2026-09-22 17:30:00','"22" <22>','22','8823','from-internal','SIP/22-0011','','Playback','demo',3,3,'ANSWERED','1727001100.1','1727001100.1');

-- 11) Ring group 600: two Local;2 legs, one answered by 101
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-21 15:00:00','"02133332222" <02133332222>','02133332222','600','from-pstn','SIP/11577-0012','Local/101@from-internal-001c;2','Dial','Local/101@from-internal&Local/102@from-internal,20,Tt',18,15,'ANSWERED','1727001200.1','1727001200.1'),
 ('2026-09-21 15:00:00','"02133332222" <02133332222>','02133332222','101','from-internal','Local/101@from-internal-001c;1','SIP/101-0012','Dial','SIP/101,,Tt',18,15,'ANSWERED','1727001200.2','1727001200.1');

-- 12) Direct DID call 0s BUSY -> missed, with 44-byte stub on disk
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-22 14:00:00','"02177778888" <02177778888>','02177778888','104','from-did-direct','SIP/11577-0013','SIP/104-0013','Dial','SIP/104,,Tt',8,0,'BUSY','1727001300.1','1727001300.1');

-- 13) Older-day calls for range coverage
INSERT INTO cdr (calldate,clid,src,dst,dcontext,channel,dstchannel,lastapp,lastdata,duration,billsec,disposition,uniqueid,linkedid) VALUES
 ('2026-09-17 09:00:00','"101" <101>','101','02112345678','from-internal','SIP/101-0014','SIP/11577-0014','Dial','SIP/11577/02112345678,,Tt',120,110,'ANSWERED','1727001400.1','1727001400.1');

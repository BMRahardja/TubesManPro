USE master;
GO

-- Jika database MIBD sudah ada, hapus dulu
IF EXISTS (SELECT * FROM sys.databases WHERE name = 'MIBD')
BEGIN
    ALTER DATABASE MIBD SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE MIBD;
END
GO

-- Buat database baru
CREATE DATABASE MIBD;
GO

-- **Pindah konteks ke MIBD**
USE MIBD;
GO

-- 1) Hapus FOREIGN KEY constraints yang masih mengikat Channel
IF OBJECT_ID('FK_Video_Channel', 'F') IS NOT NULL
    ALTER TABLE dbo.Video    DROP CONSTRAINT FK_Video_Channel;
IF OBJECT_ID('FK_AdaRole_Channel', 'F') IS NOT NULL
    ALTER TABLE dbo.AdaRole   DROP CONSTRAINT FK_AdaRole_Channel;
IF OBJECT_ID('FK_Subscribe_Channel', 'F') IS NOT NULL
    ALTER TABLE dbo.Subscribe DROP CONSTRAINT FK_Subscribe_Channel;

-- 2) Hapus FOREIGN KEY constraints yang masih mengikat Video
IF OBJECT_ID('FK_View_Video', 'F') IS NOT NULL
    ALTER TABLE dbo.[View]    DROP CONSTRAINT FK_View_Video;
IF OBJECT_ID('FK_Reaksi_Video', 'F') IS NOT NULL
    ALTER TABLE dbo.Reaksi    DROP CONSTRAINT FK_Reaksi_Video;
IF OBJECT_ID('FK_Komen_Video', 'F') IS NOT NULL
    ALTER TABLE dbo.Komen     DROP CONSTRAINT FK_Komen_Video;
GO

-- 3) Drop tabel dari child → parent
IF OBJECT_ID('dbo.Komen',      'U') IS NOT NULL DROP TABLE dbo.Komen;
IF OBJECT_ID('dbo.Reaksi',     'U') IS NOT NULL DROP TABLE dbo.Reaksi;
IF OBJECT_ID('dbo.[View]',     'U') IS NOT NULL DROP TABLE dbo.[View];
IF OBJECT_ID('dbo.Subscribe',  'U') IS NOT NULL DROP TABLE dbo.Subscribe;
IF OBJECT_ID('dbo.AdaRole',    'U') IS NOT NULL DROP TABLE dbo.AdaRole;
IF OBJECT_ID('dbo.Invite',     'U') IS NOT NULL DROP TABLE dbo.Invite;
IF OBJECT_ID('dbo.Video',      'U') IS NOT NULL DROP TABLE dbo.Video;
IF OBJECT_ID('dbo.Channel',    'U') IS NOT NULL DROP TABLE dbo.Channel;
IF OBJECT_ID('dbo.BrandAcc',   'U') IS NOT NULL DROP TABLE dbo.BrandAcc;
IF OBJECT_ID('dbo.[User]',     'U') IS NOT NULL DROP TABLE dbo.[User];
GO

CREATE TABLE dbo.[User] (
    userId    INT IDENTITY(1,1) PRIMARY KEY,
	username  VARCHAR(15) not null,
    email     VARCHAR(50)  NOT NULL UNIQUE,
    pass      VARCHAR(15)  NOT NULL,
);
GO

CREATE TABLE dbo.BrandAcc (
    baId    INT           IDENTITY(1,1) PRIMARY KEY,
    username VARCHAR(15) NOT NULL UNIQUE,
    userId  INT           NOT NULL,
	pass    VARCHAR(15)  NOT NULL,
    CONSTRAINT FK_BrandAcc_User FOREIGN KEY(userId) REFERENCES dbo.[User](userId)
);
GO

CREATE TABLE dbo.Channel (
    chnlId  INT             IDENTITY(1,1) PRIMARY KEY,
    banner  VARCHAR(50)   NULL,
    nama    VARCHAR(15)   NOT NULL,
    [desc]  VARCHAR(200)   NOT NULL,
    pfp     VARCHAR(50)   NOT NULL,
    tipe    VARCHAR(8)    NOT NULL,
    baId    INT             NULL,
    userId  INT             NULL,
    CONSTRAINT FK_Channel_BrandAcc FOREIGN KEY(baId)   REFERENCES dbo.BrandAcc(baId),
    CONSTRAINT FK_Channel_User     FOREIGN KEY(userId) REFERENCES dbo.[User](userId)
);
GO

CREATE TABLE dbo.Video (
    videoId   INT IDENTITY(1,1) PRIMARY KEY,
    tglUpld   DATE      NOT NULL,
    judul     VARCHAR(50) NOT NULL,
    [desc]    VARCHAR(200) NULL,
    status    VARCHAR(4)  NOT NULL,
    chnlId    INT           NOT NULL,
    userId    INT           NOT NULL,
    thumbnail VARCHAR(50) NOT NULL,
    subtitle  VARCHAR(50) NULL,
	playback  VARCHAR(50) NOT NULL,
    CONSTRAINT FK_Video_Channel FOREIGN KEY(chnlId) REFERENCES dbo.Channel(chnlId),
    CONSTRAINT FK_Video_User    FOREIGN KEY(userId) REFERENCES dbo.[User](userId)
);
GO

CREATE TABLE dbo.Invite (
    kirimId   INT           NOT NULL,
    terimaId  INT           NOT NULL,
    role      VARCHAR(14)  NOT NULL,
    CONSTRAINT PK_Invite PRIMARY KEY (kirimId, terimaId),
    CONSTRAINT FK_Invite_KirimUser  FOREIGN KEY(kirimId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_Invite_TerimaUser FOREIGN KEY(terimaId) REFERENCES dbo.[User](userId)
);
GO

CREATE TABLE dbo.AdaRole (
    userId   INT           NOT NULL,
    chnlId   INT           NOT NULL,
    role     VARCHAR(14)  NOT NULL,
    CONSTRAINT PK_AdaRole          PRIMARY KEY (userId, chnlId),
    CONSTRAINT FK_AdaRole_User     FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_AdaRole_Channel  FOREIGN KEY(chnlId)  REFERENCES dbo.Channel(chnlId)
);
GO

CREATE TABLE dbo.Subscribe (
    userId   INT      NOT NULL,
    chnlId   INT      NOT NULL,
    tglSub   DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT PK_Subscribe            PRIMARY KEY (userId, chnlId),
    CONSTRAINT FK_Subscribe_User       FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_Subscribe_Channel    FOREIGN KEY(chnlId)  REFERENCES dbo.Channel(chnlId)
);
GO

CREATE TABLE dbo.[View] (
    userId    INT      NOT NULL,
    videoId   INT      NOT NULL,
    tglView   DATE     NOT NULL,
    waktuView TIME     NOT NULL,
    CONSTRAINT PK_View          PRIMARY KEY (userId, videoId),
    CONSTRAINT FK_View_User     FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_View_Video    FOREIGN KEY(videoId) REFERENCES dbo.Video(videoId)
);
GO

CREATE TABLE dbo.Reaksi (
    userId  INT           NOT NULL,
    videoId INT           NOT NULL,
    tipe    VARCHAR(7)  NOT NULL,
    CONSTRAINT PK_Reaksi           PRIMARY KEY (userId, videoId),
    CONSTRAINT FK_Reaksi_User      FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_Reaksi_Video     FOREIGN KEY(videoId) REFERENCES dbo.Video(videoId)
);
GO

CREATE TABLE dbo.Komen (
    userId       INT           NOT NULL,
    videoId      INT           NOT NULL,
    konten       VARCHAR(200) NOT NULL,
    tanggalKomen DATETIME          NOT NULL,
    CONSTRAINT PK_Komen          PRIMARY KEY (userId, videoId, tanggalKomen),
    CONSTRAINT FK_Komen_User     FOREIGN KEY(userId)  REFERENCES dbo.[User](userId),
    CONSTRAINT FK_Komen_Video    FOREIGN KEY(videoId) REFERENCES dbo.Video(videoId)
);
GO

INSERT INTO dbo.[User]
VALUES 
('a', 'a@gmail.com', 'a'),
('w', 'w@gmail.com', 'w'),
('s', 's@gmail.com', 's'),
('d', 'd@gmail.com', 'd'),
('j', 'j@gmail.com', 'j')

select * from [User]



INSERT INTO BrandAcc(username, userId, pass)
VALUES 
('b', 2, 'b')

select * from BrandAcc



INSERT INTO dbo.Channel (banner, nama, [desc], pfp, tipe, baId, userId)
VALUES 
(NULL, 'My Personal', 'Personal vlog channel about daily life', '../img/thumb2.png', 'personal', NULL, 1),
(NULL, 'Tech Explor', 'Exploring latest technology trends', '../img/thumb2.png', 'personal', NULL, 2),
(NULL, 'Cooking Advent', 'Sharing my culinary journey', '../img/thumb2.png', 'personal', NULL, 3),
(NULL, 'Gaming Universe', 'Daily gaming streams and reviews', '../img/thumb2.png', 'personal', NULL, 4),
(NULL, 'Test channel', 'Test channel desc', '../img/thumb2.png', 'personal', NULL, 5),
(NULL, 'brand', 'brand', '../img/thumb2.png', 'brand', 1, 2);

select * from Channel




INSERT INTO dbo.Video (tglUpld, judul, [desc], status, chnlId, userId, thumbnail, subtitle, playback)
VALUES
(GETDATE(), 'My First Vlog', 'Sharing my first day as a content creator', 'up', 1, 1, '../img/thumb1.png', NULL, '../Videos/video_1.mp4'),
(GETDATE(), 'huh', 'h', 'up', 1, 1, '../img/thumb7.png', NULL, '../Videos/video_2.mp4'),
(GETDATE(), 'Tech Review: New Smartphone', 'Unboxing and testing the latest smartphone', 'up', 2, 2, '../img/thumb2.png', NULL, '../Videos/video_3.mp4'),
(GETDATE(), 'Easy Pasta Recipe', 'Quick dinner recipe you can make in 15 minutes', 'up', 3, 3, '../img/thumb3.png', NULL, '../Videos/video_4.mp4'),
(GETDATE(), 'Gameplay: Level 1-5 Walkthrough', 'Beginner guide for new players', 'up', 4, 4, '../img/thumb4.png', NULL, '../Videos/video_5.mp4'),
(GETDATE(), 'Test title', 'Test desc', 'up', 5, 5, '../img/thumb5.png', NULL, '../Videos/video_6.mp4'),
(GETDATE(), 'Test title', 'Test desc', 'up', 5, 5, '../img/thumb6.png', NULL, '../Videos/video_7.mp4'),
(GETDATE(), 'BA Test title', 'BA Test desc', 'up', 6, 2, '../img/thumb7.png', NULL, '../Videos/video_7.mp4');

select * from Video


INSERT INTO dbo.[View] (userId, videoId, tglView, waktuView)
VALUES
(2, 1, '2024-06-01', '14:30:00'),
(2, 2, '2024-06-02', '16:45:00'),
(1, 1, '2024-06-03', '10:15:00'),
(1, 3, '2024-06-04', '19:20:00'),
(3, 1, '2024-06-04', '19:20:00');

select * from [View]



INSERT INTO Reaksi
VALUES
(2, 1, 'like'),
(2, 2, 'dislike'),
(1, 1, 'dislike'),
(1, 3, 'like'),
(3, 1, 'dislike')

select * from Reaksi



insert into Komen
values
(1, 1, 'waw', GETDATE())

select * from Komen




select * from Subscribe



select * from Invite
select * from BrandAcc
select * from Channel
select * from AdaRole
select * from [User]

CREATE NONCLUSTERED INDEX IX_Video_chnlId 
ON Video (chnlId) 
INCLUDE (judul, tglUpld, thumbnail, status);

CREATE NONCLUSTERED INDEX IX_Video_userId 
ON Video (userId);

CREATE NONCLUSTERED INDEX IX_View_videoId 
ON [View] (videoId);

CREATE NONCLUSTERED INDEX IX_Subscribe_chnlId 
ON Subscribe (chnlId);

CREATE NONCLUSTERED INDEX IX_Subscribe_userId 
ON Subscribe (userId);

CREATE NONCLUSTERED INDEX IX_Channel_baId 
ON Channel (baId);

CREATE NONCLUSTERED INDEX IX_Channel_userId 
ON Channel (userId);

CREATE NONCLUSTERED INDEX IX_Reaksi_videoId 
ON Reaksi (videoId);

CREATE NONCLUSTERED INDEX IX_User_email 
ON [User] (email);

CREATE NONCLUSTERED INDEX IX_Komen_videoId 
ON Komen (videoId);

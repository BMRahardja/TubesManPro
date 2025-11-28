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

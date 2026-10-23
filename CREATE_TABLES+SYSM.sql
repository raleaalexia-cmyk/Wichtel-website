CREATE TABLE Elf (
    pk_elf      INT AUTO_INCREMENT PRIMARY KEY,
    firstName   VARCHAR(50)  NOT NULL,
    lastName    VARCHAR(50)  NOT NULL,
    email       VARCHAR(70)  NOT NULL UNIQUE,
    elfStatus   VARCHAR(20)  NOT NULL DEFAULT 'New'
);

CREATE TABLE Action (
    pk_action       INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(30)  NOT NULL,
    firstName       VARCHAR(30)  NOT NULL,
    lastName        VARCHAR(50)  NOT NULL,
    email           VARCHAR(70)  NOT NULL,
    startDate       DATE         NOT NULL,
    endDate         DATE         NOT NULL,
    targetValue     INT          NOT NULL,
    actionPassword  VARCHAR(255) NOT NULL,
    fk_elf_create   INT          NOT NULL,
    actionStatus    VARCHAR(20)  NOT NULL DEFAULT 'Draft',
    allowWishlist   BOOLEAN      NOT NULL DEFAULT false,
    CONSTRAINT fk_action_creator
        FOREIGN KEY (fk_elf_create)
        REFERENCES Elf(pk_elf),
    CONSTRAINT chk_action_dates
        CHECK (startDate <= endDate)
);

CREATE TABLE Invite (
    pkfk_action      INT  NOT NULL,
    pkfk_elf         INT  NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'AddedToList',
    wishDescription  TEXT,
    PRIMARY KEY (pkfk_action, pkfk_elf),
    CONSTRAINT fk_invite_action
        FOREIGN KEY (pkfk_action)
        REFERENCES Action(pk_action)
        ON DELETE CASCADE,
    CONSTRAINT fk_invite_elf
        FOREIGN KEY (pkfk_elf)
        REFERENCES Elf(pk_elf)
        ON DELETE CASCADE
);

CREATE TABLE Draw (
    pkfk_action        INT       NOT NULL,
    pkfk_elf_gives     INT       NOT NULL,
    pkfk_elf_receives  INT       NOT NULL,
    createDate         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pkfk_action, pkfk_elf_gives),
    CONSTRAINT fk_draw_action
        FOREIGN KEY (pkfk_action)
        REFERENCES Action(pk_action)
        ON DELETE CASCADE,
    CONSTRAINT fk_draw_giver
        FOREIGN KEY (pkfk_elf_gives)
        REFERENCES Elf(pk_elf)
        ON DELETE CASCADE,
    CONSTRAINT fk_draw_receiver
        FOREIGN KEY (pkfk_elf_receives)
        REFERENCES Elf(pk_elf)
        ON DELETE CASCADE,
    UNIQUE (pkfk_elf_gives,    pkfk_action),
    UNIQUE (pkfk_elf_receives, pkfk_action),
    CONSTRAINT chk_giver_receiver_different
        CHECK (pkfk_elf_gives <> pkfk_elf_receives)
);

CREATE TABLE sys_User (
    pk_user          INT AUTO_INCREMENT PRIMARY KEY,
    userName         VARCHAR(50)  NOT NULL UNIQUE,
    password         VARCHAR(255) NOT NULL,
    role             ENUM('Admin', 'Elf') NOT NULL,
    fk_elf_registers INT NULL,
    CONSTRAINT fk_user_elf
        FOREIGN KEY (fk_elf_registers)
        REFERENCES Elf(pk_elf)
        ON DELETE CASCADE,
    CONSTRAINT uq_user_elf
        UNIQUE (fk_elf_registers)
);

CREATE TABLE sys_StateMachine (
    pk_state    INT AUTO_INCREMENT PRIMARY KEY,
    entity      VARCHAR(20) NOT NULL,
    predecessor VARCHAR(20) NULL,
    successor   VARCHAR(20) NULL,
    status      VARCHAR(10) NOT NULL DEFAULT 'active',
    CONSTRAINT chk_sm_entity
        CHECK (entity IN ('Elf', 'NimmtTeil', 'Action'))
);

INSERT INTO sys_StateMachine (entity, predecessor, successor) VALUES
-- Elf
('Elf', NULL,            'New'),
('Elf', 'New',           'Active'),
('Elf', 'Active',        'Suspended'),
('Elf', 'Suspended',     'Active'),
('Elf', 'Active',        'Unsubscribed'),
('Elf', 'Unsubscribed',  NULL),
-- NimmtTeil (Invite)
('NimmtTeil', NULL,          'AddedToList'),
('NimmtTeil', 'AddedToList', 'Invited'),
('NimmtTeil', 'AddedToList', 'Cancelled'),
('NimmtTeil', 'Invited',     'Accepted'),
('NimmtTeil', 'Invited',     'Declined'),
('NimmtTeil', 'Invited',     'Cancelled'),
('NimmtTeil', 'Accepted',    NULL),
('NimmtTeil', 'Declined',    NULL),
('NimmtTeil', 'Cancelled',   NULL),
-- Action
('Action', NULL,            'Draft'),
('Action', 'Draft',         'Started'),
('Action', 'Draft',         'Cancelled'),
('Action', 'Started',       'ReadyForDraw'),
('Action', 'Started',       'Cancelled'),
('Action', 'ReadyForDraw',  'Drawn'),
('Action', 'Drawn',         'Closed'),
('Action', 'Closed',        NULL);
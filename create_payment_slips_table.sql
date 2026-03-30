-- ======================================================
-- สร้างตาราง payment_slips สำหรับระบบตรวจสลิปอัตโนมัติ
-- รัน: mysql -u root -p backoffice_db < create_payment_slips_table.sql
-- ======================================================

CREATE TABLE IF NOT EXISTS payment_slips (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id       INT UNSIGNED NOT NULL,
    booking_ref      VARCHAR(30)  NOT NULL,

    -- รูปสลิป
    slip_image_path  VARCHAR(500) NOT NULL,
    slip_hash        VARCHAR(64)  NOT NULL COMMENT 'SHA-256 ของไฟล์ กันสลิปซ้ำ',

    -- ข้อมูลที่ AI อ่านได้จากสลิป
    extracted_amount            DECIMAL(10,2) NULL,
    extracted_transfer_datetime DATETIME      NULL,
    extracted_ref_no            VARCHAR(200)  NULL COMMENT 'เลขอ้างอิงธุรกรรม',
    payer_name                  VARCHAR(200)  NULL,
    payer_account               VARCHAR(100)  NULL,
    payee_name                  VARCHAR(200)  NULL,
    payee_account               VARCHAR(100)  NULL,
    source_bank                 VARCHAR(100)  NULL,
    destination_bank            VARCHAR(100)  NULL,
    transfer_type               VARCHAR(50)   NULL COMMENT 'promptpay / transfer / qr',
    confidence_score            DECIMAL(4,3)  NULL COMMENT '0.0-1.0',

    -- ผลการตรวจ
    verification_status  ENUM('uploaded','checking','paid','rejected','duplicate','suspicious','manual_review')
                         NOT NULL DEFAULT 'uploaded',
    verification_reason  VARCHAR(500) NULL,

    -- ข้อมูลการอัปโหลด
    uploaded_ip  VARCHAR(45)  NULL,
    uploaded_ua  VARCHAR(500) NULL COMMENT 'User-Agent ของ browser',
    uploaded_at  DATETIME     NOT NULL,

    -- Admin ตรวจมือ
    reviewed_by  INT  NULL,
    reviewed_at  DATETIME NULL,

    -- Raw response จาก AI
    raw_ai_response TEXT NULL,

    -- Indexes
    UNIQUE KEY uq_slip_hash    (slip_hash),
    INDEX idx_booking_id       (booking_id),
    INDEX idx_booking_ref      (booking_ref),
    INDEX idx_extracted_ref    (extracted_ref_no),
    INDEX idx_status           (verification_status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- อัปเดต payment_status ใน boat_bookings ให้รองรับสถานะใหม่
ALTER TABLE boat_bookings
    MODIFY COLUMN payment_status
        ENUM('pending','waiting_verify','checking','paid','failed','expired','duplicate','suspicious','manual_review')
        NOT NULL DEFAULT 'pending';

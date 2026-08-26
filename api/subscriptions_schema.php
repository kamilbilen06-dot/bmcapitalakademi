<?php
/**
 * WhatsApp grubu aboneliği — şema.
 *
 * Kurs ödemelerinden ayrı tutulur (payment_orders kullanılmaz).
 * iyzico Subscription ürünü kartı saklar ve döneme göre çeker.
 */
require_once __DIR__ . '/egitmen_schema.php';
require_once __DIR__ . '/payments_schema.php';

function subscriptions_ensure_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        conversation_id VARCHAR(64) NOT NULL DEFAULT '',
        provider_token VARCHAR(255) NOT NULL DEFAULT '',
        iyzico_subscription_ref VARCHAR(80) NOT NULL DEFAULT '',
        iyzico_customer_ref VARCHAR(80) NOT NULL DEFAULT '',
        iyzico_plan_ref VARCHAR(80) NOT NULL DEFAULT '',
        student_name VARCHAR(160) NOT NULL DEFAULT '',
        student_email VARCHAR(190) NOT NULL DEFAULT '',
        student_phone VARCHAR(40) NOT NULL DEFAULT '',
        amount_kurus INT NOT NULL DEFAULT 0,
        interval_unit VARCHAR(16) NOT NULL DEFAULT 'MONTHLY',
        status VARCHAR(24) NOT NULL DEFAULT 'pending',
        current_period_end DATETIME NULL,
        last_paid_at DATETIME NULL,
        last_failure_at DATETIME NULL,
        cancelled_at DATETIME NULL,
        wa_added TINYINT(1) NOT NULL DEFAULT 0,
        error_message VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        INDEX idx_sub_student (student_id),
        INDEX idx_sub_status (status),
        INDEX idx_sub_ref (iyzico_subscription_ref),
        INDEX idx_sub_token (provider_token),
        INDEX idx_sub_conv (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    egitmen_add_column_if_missing(
        $pdo,
        'subscriptions',
        'mail_sent_at',
        'DATETIME NULL AFTER cancelled_at'
    );
    egitmen_add_column_if_missing(
        $pdo,
        'subscriptions',
        'cancel_mail_at',
        'DATETIME NULL AFTER mail_sent_at'
    );
    egitmen_add_column_if_missing(
        $pdo,
        'subscriptions',
        'past_due_mail_at',
        'DATETIME NULL AFTER cancel_mail_at'
    );
    egitmen_add_column_if_missing(
        $pdo,
        'subscriptions',
        'instructor_id',
        'INT NOT NULL DEFAULT 0 AFTER student_id'
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subscription_id INT NOT NULL,
        order_reference VARCHAR(80) NOT NULL DEFAULT '',
        amount_kurus INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_sub_order_ref (order_reference),
        INDEX idx_inv_sub (subscription_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    egitmen_add_column_if_missing(
        $pdo,
        'subscription_invoices',
        'provider_payment_id',
        "VARCHAR(32) NOT NULL DEFAULT '' AFTER order_reference"
    );

    subscriptions_seed_settings($pdo);
}

function subscriptions_seed_settings(PDO $pdo): void {
    $defaults = [
        'sub_enabled' => '1',
        'sub_title' => 'WhatsApp analiz grubu',
        'sub_price' => '199',
        'sub_blurb' => 'Aylık WhatsApp analiz grubu üyeliği. Kartınız her dönemde otomatik çekilir; istediğiniz an siteden iptal edebilirsiniz. Gruba ekleme ve çıkarma yönetici tarafından yapılır.',
        'sub_instructor_id' => '0',
        'satici_unvan' => '',
        'satici_adres' => '',
        'satici_vergi' => '',
        'satici_mersis' => '',
        'nav_hakkimizda' => '0',
        'nav_sss' => '0',
        'nav_iletisim' => '0',
        'nav_araclar' => '0',
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = v");
    foreach ($defaults as $k => $v) {
        $stmt->execute([$k, $v]);
    }
    try {
        $pdo->prepare("UPDATE faqs SET answer = ? WHERE question = ?")
            ->execute([
                'Kart ile ödeme iyzico üzerinden yapılır (odeme.php). Havale/EFT seçerseniz dekontu iletmeniz gerekir; yönetici onayından sonra erişim açılır.',
                'Ödeme nasıl yapılıyor?',
            ]);
    } catch (Throwable $e) {
        // faqs henüz yoksa sessiz
    }
}

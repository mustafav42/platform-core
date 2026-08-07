<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_permission('maintenance.manage');

$pageTitle = 'Backup & Restore Center';
$currentPage = 'backup';

$pdo = ent_db();
$backup = new BackupService($pdo);
$notice = trim((string)($_GET['notice'] ?? ''));
$error = '';

function backup_human_size(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
}

function backup_type_label(string $type): string
{
    return match (true) {
        str_contains($type, 'full-system') => 'Tam Sistem',
        str_contains($type, 'pre-restore') => 'Acil Geri Dönüş',
        str_contains($type, 'database') => 'Veritabanı',
        default => 'Yedek',
    };
}

function backup_redirect_notice(string $message): never
{
    ent_redirect('backup.php?notice=' . rawurlencode($message));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ent_verify_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'create_database') {
            $created = $backup->createDatabaseBackup();
            backup_redirect_notice('Veritabanı yedeği oluşturuldu: ' . $created['file_name']);
        }

        if ($action === 'create_full') {
            $created = $backup->createFullBackup();
            backup_redirect_notice('Tam sistem yedeği oluşturuldu: ' . $created['file_name']);
        }

        if ($action === 'upload_backup') {
            $created = $backup->importUpload($_FILES['backup_file'] ?? []);
            backup_redirect_notice('Yedek sisteme yüklendi: ' . $created['file_name']);
        }

        if ($action === 'delete_backup') {
            $fileName = basename((string)($_POST['file_name'] ?? ''));
            $backup->deleteBackup($fileName);
            backup_redirect_notice('Yedek silindi.');
        }

        if ($action === 'restore_backup') {
            $fileName = basename((string)($_POST['file_name'] ?? ''));
            $pin = (string)($_POST['pin'] ?? '');
            $confirmation = (string)($_POST['confirmation'] ?? '');

            if ($confirmation !== 'RESTORE') {
                throw new RuntimeException('Geri yükleme onayı tamamlanmadı.');
            }
            if (!$backup->verifyCurrentActorPin($pin)) {
                throw new RuntimeException('Yönetici PIN doğrulaması başarısız.');
            }

            $result = $backup->restoreBackup($fileName);

            // Restored user/permission tables may differ. Force a clean login.
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            header(
                'Location: ../?restored=' . rawurlencode((string)$result['restored'])
                . '&emergency=' . rawurlencode((string)$result['emergency_backup']),
                true,
                303
            );
            exit;
        }

        throw new RuntimeException('Geçersiz işlem.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (function_exists('audit_log')) {
            audit_log('backup_center_error', 'Backup & Restore Center işlemi başarısız.', [
                'error' => $e->getMessage(),
                'action' => (string)($_POST['action'] ?? ''),
            ]);
        }
    }
}

if (isset($_GET['download'])) {
    try {
        $token = (string)($_GET['csrf_token'] ?? '');
        if ($token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
            throw new RuntimeException('İndirme güvenlik doğrulaması başarısız.');
        }
        $fileName = basename((string)$_GET['download']);
        $path = $backup->resolveBackupPath($fileName);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
        header('Content-Length: ' . (string)filesize($path));
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$backups = $backup->listBackups(100);
$latest = $backups[0] ?? null;
$totalSize = array_sum(array_map(static fn(array $b): int => (int)$b['file_size'], $backups));
$databaseCount = count(array_filter($backups, static fn(array $b): bool => !str_contains((string)$b['backup_type'], 'full-system')));
$fullCount = count(array_filter($backups, static fn(array $b): bool => str_contains((string)$b['backup_type'], 'full-system')));
$uploadLimit = (string)ini_get('upload_max_filesize');

require __DIR__ . '/_header.php';
?>
<style>
.backup-center{display:grid;gap:18px}
.backup-hero{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;padding:24px;border-radius:20px;background:linear-gradient(135deg,#fff,#fbf6f7);border:1px solid var(--ch-border,#e7e1dc)}
.backup-hero h2{margin:4px 0 8px;font-size:28px;letter-spacing:-.03em}.backup-hero p{margin:0;color:var(--ch-muted,#746b65);max-width:760px}
.backup-hero-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}.backup-hero-actions form{margin:0}
.backup-btn{min-height:42px;border:0;border-radius:12px;padding:10px 15px;font-weight:850;cursor:pointer}.backup-btn-primary{background:#92263a;color:#fff}.backup-btn-secondary{background:#fff;color:#211d1a;border:1px solid #e7e1dc}.backup-btn-danger{background:#fff0f2;color:#a63a48;border:1px solid #ffd9de}
.backup-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.backup-stat{padding:18px;border:1px solid #e7e1dc;border-radius:16px;background:#fff}.backup-stat small{display:block;color:#746b65;margin-bottom:6px}.backup-stat strong{font-size:21px}
.backup-notice,.backup-error{padding:13px 15px;border-radius:14px;font-weight:700}.backup-notice{background:#eaf7f1;color:#176548;border:1px solid #cdeadd}.backup-error{background:#fff0f2;color:#a63a48;border:1px solid #ffd9de}
.backup-panel{padding:20px;border:1px solid #e7e1dc;border-radius:18px;background:#fff}.backup-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:15px}.backup-panel-head h3{margin:0 0 4px;font-size:18px}.backup-panel-head p{margin:0;color:#746b65;font-size:13px}
.backup-table-wrap{overflow:auto}.backup-table{width:100%;border-collapse:collapse}.backup-table th,.backup-table td{padding:12px 10px;border-bottom:1px solid #eee8e3;text-align:left;white-space:nowrap}.backup-table th{font-size:11px;color:#746b65;text-transform:uppercase;letter-spacing:.04em}.backup-table tr:last-child td{border-bottom:0}
.backup-type{display:inline-flex;padding:5px 9px;border-radius:999px;background:#f8edf0;color:#7d1f31;font-size:11px;font-weight:850}.backup-actions{display:flex;gap:7px;justify-content:flex-end}.backup-actions form{margin:0}
.backup-link{display:inline-flex;align-items:center;min-height:34px;padding:7px 10px;border-radius:9px;border:1px solid #e7e1dc;text-decoration:none;color:#211d1a;font-weight:750;font-size:12px;background:#fff}
.backup-upload{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:end}.backup-upload label{display:grid;gap:7px;font-weight:750}.backup-upload input[type=file]{min-height:42px;padding:8px;border:1px solid #e7e1dc;border-radius:12px;background:#fff}
.backup-info{display:grid;grid-template-columns:1fr 1fr;gap:14px}.backup-info article{padding:17px;border-radius:15px;background:#fbfaf8;border:1px solid #e7e1dc}.backup-info h4{margin:0 0 7px}.backup-info p{margin:0;color:#746b65;font-size:13px;line-height:1.55}
.restore-modal{position:fixed;inset:0;display:none;place-items:center;background:rgba(28,22,20,.46);z-index:9999;padding:18px}.restore-modal.is-open{display:grid}.restore-card{width:min(520px,100%);background:#fff;border-radius:20px;padding:22px;box-shadow:0 30px 80px rgba(0,0,0,.22)}.restore-card h3{margin:0 0 8px}.restore-card p{color:#746b65;line-height:1.55}.restore-warning{padding:12px;border-radius:12px;background:#fff5e5;color:#805214;margin:14px 0}.restore-form{display:grid;gap:12px}.restore-form label{display:grid;gap:6px;font-weight:750}.restore-form input{min-height:42px;border:1px solid #e7e1dc;border-radius:11px;padding:10px 12px}.restore-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:4px}
@media(max-width:950px){.backup-hero{grid-template-columns:1fr}.backup-hero-actions{justify-content:flex-start}.backup-stats{grid-template-columns:1fr 1fr}.backup-info{grid-template-columns:1fr}}
@media(max-width:620px){.backup-stats{grid-template-columns:1fr}.backup-upload{grid-template-columns:1fr}.backup-actions{justify-content:flex-start;flex-wrap:wrap}.backup-hero-actions .backup-btn{width:100%}}
</style>

<div class="backup-center ch-content">
    <section class="backup-hero">
        <div>
            <span class="ch-eyebrow">WORKSPACE CORE · DATA PROTECTION</span>
            <h2>Backup & Restore Center</h2>
            <p>Veritabanı ve kullanıcı üretimi dosyaları güvenli şekilde yedekleyin; gerektiğinde PIN doğrulaması ve otomatik acil geri dönüş noktasıyla sistemi eski durumuna döndürün.</p>
        </div>
        <div class="backup-hero-actions">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?=ent_e((string)$_SESSION['csrf_token'])?>">
                <input type="hidden" name="action" value="create_database">
                <button class="backup-btn backup-btn-secondary" type="submit">Veritabanı Yedeği</button>
            </form>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?=ent_e((string)$_SESSION['csrf_token'])?>">
                <input type="hidden" name="action" value="create_full">
                <button class="backup-btn backup-btn-primary" type="submit" <?=$backup->supportsFullBackup()?'':'disabled title="PHP ZIP uzantısı gerekli"'?>>Tam Sistem Yedeği</button>
            </form>
        </div>
    </section>

    <?php if ($notice !== ''): ?><div class="backup-notice"><?=ent_e($notice)?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="backup-error"><?=ent_e($error)?></div><?php endif; ?>

    <section class="backup-stats">
        <article class="backup-stat"><small>Son Yedek</small><strong><?=$latest ? ent_e(date('d.m.Y H:i', strtotime((string)$latest['created_at']))) : 'Henüz yok'?></strong></article>
        <article class="backup-stat"><small>Veritabanı Yedeği</small><strong><?=number_format($databaseCount)?></strong></article>
        <article class="backup-stat"><small>Tam Sistem Yedeği</small><strong><?=number_format($fullCount)?></strong></article>
        <article class="backup-stat"><small>Toplam Alan</small><strong><?=ent_e(backup_human_size((int)$totalSize))?></strong></article>
    </section>

    <section class="backup-panel">
        <div class="backup-panel-head">
            <div><h3>Yedek Geçmişi</h3><p>Sunucuda saklanan ve daha önce oluşturulmuş yedekler.</p></div>
            <span class="backup-type"><?=count($backups)?> kayıt</span>
        </div>
        <div class="backup-table-wrap">
            <table class="backup-table">
                <thead><tr><th>Tarih</th><th>Tür</th><th>Dosya</th><th>Boyut</th><th>Durum</th><th style="text-align:right">İşlemler</th></tr></thead>
                <tbody>
                <?php foreach ($backups as $item): ?>
                    <tr>
                        <td><?=ent_e(date('d.m.Y H:i', strtotime((string)$item['created_at'])))?></td>
                        <td><span class="backup-type"><?=ent_e(backup_type_label((string)$item['backup_type']))?></span></td>
                        <td><?=ent_e((string)$item['file_name'])?></td>
                        <td><?=ent_e(backup_human_size((int)$item['file_size']))?></td>
                        <td><?=ent_e((string)$item['status'])?></td>
                        <td>
                            <div class="backup-actions">
                                <a class="backup-link" href="?download=<?=rawurlencode((string)$item['file_name'])?>&csrf_token=<?=rawurlencode((string)$_SESSION['csrf_token'])?>">İndir</a>
                                <button class="backup-link" type="button" data-restore="<?=ent_e((string)$item['file_name'])?>">Geri Yükle</button>
                                <form method="post" onsubmit="return confirm('Bu yedek kalıcı olarak silinsin mi?')">
                                    <input type="hidden" name="csrf_token" value="<?=ent_e((string)$_SESSION['csrf_token'])?>">
                                    <input type="hidden" name="action" value="delete_backup">
                                    <input type="hidden" name="file_name" value="<?=ent_e((string)$item['file_name'])?>">
                                    <button class="backup-link backup-btn-danger" type="submit">Sil</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$backups): ?>
                    <tr><td colspan="6" style="text-align:center;color:#746b65;padding:28px">Henüz yedek bulunmuyor.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="backup-panel">
        <div class="backup-panel-head">
            <div><h3>Harici Yedeği İçeri Al</h3><p>Bilgisayarınızda tuttuğunuz CherryHouse yedeğini tekrar sisteme yükleyin. Maksimum PHP yükleme limiti: <?=ent_e($uploadLimit)?></p></div>
        </div>
        <form class="backup-upload" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?=ent_e((string)$_SESSION['csrf_token'])?>">
            <input type="hidden" name="action" value="upload_backup">
            <label>Yedek Dosyası
                <input type="file" name="backup_file" accept=".sql,.gz,.zip,application/sql,application/zip,application/gzip" required>
            </label>
            <button class="backup-btn backup-btn-secondary" type="submit">Sisteme Yükle</button>
        </form>
    </section>

    <section class="backup-info">
        <article><h4>Veritabanı Yedeği</h4><p>Ürünler, kategoriler, masalar, siparişler, ödemeler, ayarlar, kullanıcılar ve veritabanında tutulan diğer işletme kayıtlarını içerir. Dosya biçimi <b>.sql</b> veya destek varsa <b>.sql.gz</b> olur.</p></article>
        <article><h4>Tam Sistem Yedeği</h4><p>Veritabanına ek olarak yüklenen ürün görselleri, medya kütüphanesi, marka dosyaları ve salon tasarım verilerini içerir. Uygulama kaynak kodu ve gizli veritabanı şifreleri pakete alınmaz; kodun doğruluk kaynağı GitHub repository'dir.</p></article>
        <article><h4>Güvenli Geri Yükleme</h4><p>Restore işlemi başlamadan önce sistem otomatik olarak <b>restore-before</b> adlı acil geri dönüş yedeği oluşturur. İşlem ayrıca mevcut yönetici PIN'i ile doğrulanır.</p></article>
        <article><h4>Kaynak Kod Güvenliği</h4><p>PHP uygulama dosyalarının yedeği Backup Center yerine GitHub'da tutulur. Bu ayrım hem güvenliği artırır hem de eski kod sürümünün yanlışlıkla canlı sisteme geri yüklenmesini engeller.</p></article>
    </section>
</div>

<div class="restore-modal" data-restore-modal>
    <div class="restore-card">
        <h3>Yedeği Geri Yükle</h3>
        <p><b data-restore-name></b> yedeğine dönmek üzeresiniz.</p>
        <div class="restore-warning">Restore başlamadan önce sistem otomatik acil yedek oluşturacaktır. İşlem tamamlandığında güvenlik için oturumunuz kapatılır ve tekrar PIN ile giriş yaparsınız.</div>
        <form class="restore-form" method="post">
            <input type="hidden" name="csrf_token" value="<?=ent_e((string)$_SESSION['csrf_token'])?>">
            <input type="hidden" name="action" value="restore_backup">
            <input type="hidden" name="file_name" value="" data-restore-file>
            <input type="hidden" name="confirmation" value="RESTORE">
            <label>4 Haneli Yönetici PIN
                <input type="password" name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off" required>
            </label>
            <div class="restore-actions">
                <button class="backup-btn backup-btn-secondary" type="button" data-restore-close>İptal</button>
                <button class="backup-btn backup-btn-danger" type="submit">Geri Yüklemeyi Başlat</button>
            </div>
        </form>
    </div>
</div>

<script>
(() => {
    const modal = document.querySelector('[data-restore-modal]');
    const fileInput = modal?.querySelector('[data-restore-file]');
    const name = modal?.querySelector('[data-restore-name]');
    document.querySelectorAll('[data-restore]').forEach(button => {
        button.addEventListener('click', () => {
            if (!modal || !fileInput || !name) return;
            const file = button.getAttribute('data-restore') || '';
            fileInput.value = file;
            name.textContent = file;
            modal.classList.add('is-open');
            modal.querySelector('input[name="pin"]')?.focus();
        });
    });
    document.querySelector('[data-restore-close]')?.addEventListener('click', () => modal?.classList.remove('is-open'));
    modal?.addEventListener('click', event => {
        if (event.target === modal) modal.classList.remove('is-open');
    });
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>

<?php
/**
 * choose_module.php – Página de seleção de módulos
 * Segue o visual "elegante, moderno e soft" já utilizado no projeto *fatura*:
 *   • Bootstrap 5
 *   • folha de estilo em assets/css/style.css
 *   • layout consistente com includes/navbar.php
 */
require_once __DIR__ . '/config/config.php';
// Caso possua classes de usuário helper, ajuste o caminho abaixo
require_once __DIR__ . '/classes/User.php';

session_start();

// Garantir que o usuário esteja logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Conexão PDO $db vem de config/config.php
$db = $pdo ?? $conn ?? $mysqli ?? null; // adequar conforme seu arquivo config
if (!$db) {
    // fallback: cria uma instância PDO manual (ajuste para sua realidade)
    $db = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/**
 * Recupera os módulos permitidos para o usuário.
 * Se o papel for admin, retorna todos os módulos cadastrados.
 */
function fetchUserModules(int $uid, PDO $db): array {
    $sql = "SELECT m.id, m.name, m.slug, m.path
              FROM users u
              JOIN roles r          ON r.id = u.role_id
              LEFT JOIN role_module rm ON rm.role_id = r.id
              LEFT JOIN modules m       ON m.id = rm.module_id
             WHERE u.id = :uid
          UNION DISTINCT
            SELECT m.id, m.name, m.slug, m.path
              FROM users u
              JOIN roles r ON r.id = u.role_id
              JOIN modules m
             WHERE u.id = :uid AND r.name = 'admin'  -- admin vê tudo";
    $stmt = $db->prepare($sql);
    $stmt->execute(['uid' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$modules = fetchUserModules($userId, $db);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Selecione o Módulo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .module-card { transition: transform .2s, box-shadow .2s; border-radius: 1rem; }
        .module-card:hover { transform: translateY(-4px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,.10) !important; }
        .module-icon { width:48px; height:48px; object-fit:contain; }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<?php /* Navbar global (mantém consistência) */
      if (file_exists(__DIR__.'/includes/navbar.php')) {
          include __DIR__.'/includes/navbar.php';
      }
?>
<main class="flex-grow-1 py-5">
    <div class="container">
        <h2 class="text-center fw-semibold mb-4">Escolha a área que deseja acessar</h2>

        <?php if (empty($modules)): ?>
            <div class="alert alert-warning text-center">Nenhum módulo foi atribuído ao seu usuário. Contate o administrador.</div>
        <?php else: ?>
            <div class="row g-4 justify-content-center">
                <?php foreach ($modules as $mod): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="<?= htmlspecialchars($mod['path']) ?>" class="text-decoration-none">
                            <div class="card shadow-sm border-0 module-card h-100 text-center p-3">
                                <?php
                                    $iconPath = 'assets/icons/' . $mod['slug'] . '.svg';
                                    $iconSrc  = file_exists($iconPath) ? $iconPath : 'assets/icons/default.svg';
                                ?>
                                <img src="<?= $iconSrc ?>" alt="" class="module-icon mx-auto">
                                <h6 class="mt-3 mb-0 text-dark fw-semibold"><?= htmlspecialchars($mod['name']) ?></h6>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php // Rodapé global, se existir
      if (file_exists(__DIR__.'/includes/footer.php')) {
          include __DIR__.'/includes/footer.php';
      }
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

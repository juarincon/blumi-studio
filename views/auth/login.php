<?php
use App\Helpers\Csrf;
$pageTitle = 'Ingresar · Blumi Studio';
require dirname(__DIR__) . '/partials/head.php';
?>
<div class="login-shell">
    <section class="login-brand-panel">
        <div class="brand-mark brand-mark-large">
            <span class="brand-word">Blumi</span>
            <span class="brand-sub">Studio</span>
        </div>
        <div class="brand-message">
            <span class="brand-spark">B</span>
            <h1>Construye mejor.<br>Reutiliza más.</h1>
            <p>Un espacio propio para convertir componentes probados en sitios web consistentes.</p>
        </div>
        <div class="brand-pills" aria-hidden="true">
            <span class="pill pill-blue"></span>
            <span class="pill pill-light"></span>
            <span class="pill pill-mid"></span>
            <span class="pill pill-yellow"></span>
            <span class="pill pill-pink"></span>
        </div>
    </section>
    <section class="login-form-panel">
        <form class="login-card" method="post" action="index.php?route=login">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <div class="login-heading">
                <span class="eyebrow">BIENVENIDO</span>
                <h2>Iniciar sesión</h2>
                <p>Accede al workspace de Blumi Studio.</p>
            </div>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <label class="field">
                <span>Correo</span>
                <input type="email" name="email" autocomplete="email" required>
            </label>
            <label class="field">
                <span>Contraseña</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="button button-primary button-large" type="submit">Entrar</button>
        </form>
    </section>
</div>
</body>
</html>

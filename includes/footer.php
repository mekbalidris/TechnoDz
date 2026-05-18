<?php
// includes/footer.php
// Closes the .container opened in header.php and finishes the page.
?>
        </div><!-- /.container -->

        <footer class="site-footer">
            <p>&copy; <?= date('Y') ?> TechnoDz &mdash; Student project</p>
        </footer>

        <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?: time() ?>"></script>
    </body>
</html>

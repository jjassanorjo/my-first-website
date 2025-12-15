<?php
// includes/footer.php
?>
    <!-- ======================= FOOTER ======================= -->
    <div class="footer">
        <div class="container">
            <div class="row">
                <div class="footer-col-1">
                    <h3>Download Our App</h3>
                    <p>Download App for Android and iOS mobile phones.</p>
                    <div class="app-logo">
                        <img src="<?php echo SITE_URL; ?>assets/images/play-store.png" alt="play store" />
                        <img src="<?php echo SITE_URL; ?>assets/images/app-store.png" alt="app store" />
                    </div>
                </div>

                <div class="footer-col-2">
                    <img src="<?php echo SITE_URL; ?>assets/images/bunique.PNG" alt="logo" />
                    <p>Official University Merchandise Store</p>
                </div>

                <div class="footer-col-3">
                    <h3>Useful Links</h3>
                    <ul>
                        <li><a href="<?php echo SITE_URL; ?>about.php">About Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>contact.php">Contact Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>products.php">All Products</a></li>
                        <li><a href="<?php echo SITE_URL; ?>account.php">My Account</a></li>
                    </ul>
                </div>

                <div class="footer-col-4">
                    <h3>Follow Us</h3>
                    <ul>
                        <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i> Youtube</a></li>
                    </ul>
                </div>
            </div>

            <hr />
            <a href="#" class="copyright">Copyright <?php echo date('Y'); ?> - <?php echo SITE_NAME; ?></a>
        </div>
    </div>

    <!-- External scripts -->
    <script src="https://unpkg.com/scrollreveal"></script>
    
    <?php if (isset($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <script src="<?php echo SITE_URL; ?>assets/js/<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
</body>
</html>
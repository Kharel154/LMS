<?php // includes/footer.php ?>
            </div>
        </main>
    </div>

    <!-- Toast notifications -->
    <div id="toast" class="toast"></div>

    <script>
        // Fermeture automatique des toasts
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type}`;
            toast.style.display = 'block';
            setTimeout(() => toast.style.display = 'none', 4000);
        }
    </script>
</body>
</html>
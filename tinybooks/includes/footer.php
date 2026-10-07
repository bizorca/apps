    </main>
</div>
<script>
// Simple flash auto-dismiss
setTimeout(() => {
    document.querySelectorAll('[data-flash]').forEach(el => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 4000);
</script>
</body>
</html>

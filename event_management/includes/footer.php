            </main>
            <footer class="footer">
                EVENTORY <span>·</span> Every Event Tells a Story.
            </footer>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=2"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const menu = document.getElementById('accountMenu');
    const trigger = document.getElementById('accountTrigger');
    const dropdown = document.getElementById('accountDropdown');
    if (!menu || !trigger || !dropdown) return;
    function closeMenu() {
        dropdown.classList.remove('show');
        trigger.setAttribute('aria-expanded', 'false');
    }
    trigger.addEventListener('click', function (event) {
        event.stopPropagation();
        const opening = !dropdown.classList.contains('show');
        dropdown.classList.toggle('show', opening);
        trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target)) closeMenu();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenu();
    });
});
</script>
</body>
</html>



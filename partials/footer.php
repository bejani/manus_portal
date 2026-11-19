</main><footer class="site-footer"><div class="container">سامانه آزمون دانش‌آموزی — نسخهٔ سازگار با PHP و MySQL</div></footer>
<dialog id="portal-dialog" class="portal-dialog" aria-labelledby="portal-dialog-title" aria-describedby="portal-dialog-message">
    <div class="portal-dialog-card">
        <div class="portal-dialog-icon" data-dialog-icon aria-hidden="true">?</div>
        <h2 id="portal-dialog-title" data-dialog-title>تأیید عملیات</h2>
        <p id="portal-dialog-message" class="portal-dialog-message" data-dialog-message></p>
        <div class="portal-dialog-input-wrap" data-dialog-input-wrap hidden>
            <label for="portal-dialog-input">دلیل یا توضیح</label>
            <input id="portal-dialog-input" class="portal-dialog-input" data-dialog-input type="text" maxlength="500" autocomplete="off">
            <p class="portal-dialog-input-error" data-dialog-input-error aria-live="polite"></p>
        </div>
        <div class="portal-dialog-actions">
            <button type="button" data-dialog-cancel>انصراف</button>
            <button type="button" data-dialog-accept>تأیید و ادامه</button>
        </div>
    </div>
</dialog>
<script src="<?= e(url('assets/portal-ui.js').'?v='.(string)filemtime(__DIR__.'/../assets/portal-ui.js')) ?>" defer></script>
</body></html>

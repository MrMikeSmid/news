<label class="check-label"><input type="checkbox" name="consent" value="1"<?= checked('consent') ?> required><span>Ik geef toestemming dat mijn gegevens gebruikt worden om mij te informeren over dit initiatief. *</span></label>
<small class="field-error"><?= e($errors['consent'] ?? '') ?></small>
<p class="privacy-note">🔒 Je gegevens worden alleen gebruikt voor Sportweek ’t Zand en niet gedeeld met derden. Lees ons <a href="<?= e(url('privacy')) ?>">privacybeleid</a>.</p>

<?php
declare(strict_types=1);

function sf_address_fields(array $values): void
{
    ?>
    <section class="sf-address-entry" data-address-entry>
        <div class="panel-head">
            <h2>Delivery address</h2>
            <button class="btn btn-secondary btn-small" type="button" data-address-current>Use current location</button>
        </div>
        <p>Enter your address manually, or use your current location to fill it.</p>
        <input type="hidden" name="latitude" value="<?= e($values['latitude'] ?? '') ?>" data-address-lat>
        <input type="hidden" name="longitude" value="<?= e($values['longitude'] ?? '') ?>" data-address-lng>
        <div class="form-group">
            <label>Address
                <textarea name="delivery_address" rows="3" minlength="10" maxlength="500" autocomplete="street-address" data-address-text required><?= e($values['delivery_address'] ?? '') ?></textarea>
            </label>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label>Pincode
                    <input name="delivery_pincode" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="postal-code" value="<?= e($values['delivery_pincode'] ?? '') ?>" data-address-postcode required>
                </label>
            </div>
            <div class="form-group">
                <label>Landmark / house number
                    <input name="landmark" maxlength="200" value="<?= e($values['landmark'] ?? '') ?>">
                </label>
            </div>
        </div>
        <p class="sf-note" role="status" aria-live="polite" data-address-status></p>
        <small class="sf-note">Using current location sends your position to Photon to find a nearby address. Please check the result. Address data © OpenStreetMap contributors.</small>
    </section>
    <?php
}

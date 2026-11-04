<?= $this->extend($config->viewLayout) ?>
<?= $this->section('main') ?>

<div class="container">
	<div class="row">
		<div class="col-sm-6 offset-sm-3">

			<div class="card">
				<h2 class="card-header"><?=lang('Auth.loginTitle')?></h2>
				<div class="card-body">

					<?= view('Myth\Auth\Views\_message_block') ?>

					<form action="<?= url_to('login') ?>" method="post">
						<?= csrf_field() ?>

<?php if ($config->validFields === ['email']): ?>
						<div class="form-group">
							<label for="login"><?=lang('Auth.email')?></label>
							<input type="email" class="form-control <?php if (session('errors.login')) : ?>is-invalid<?php endif ?>"
								   name="login" placeholder="<?=lang('Auth.email')?>">
							<div class="invalid-feedback">
								<?= session('errors.login') ?>
							</div>
						</div>
<?php else: ?>
						<div class="form-group">
							<label for="login"><?=lang('Auth.emailOrUsername')?></label>
							<input type="text" class="form-control <?php if (session('errors.login')) : ?>is-invalid<?php endif ?>"
								   name="login" placeholder="<?=lang('Auth.emailOrUsername')?>">
							<div class="invalid-feedback">
								<?= session('errors.login') ?>
							</div>
						</div>
<?php endif; ?>

						<div class="form-group">
							<label for="password"><?=lang('Auth.password')?></label>
							<input type="password" name="password" class="form-control  <?php if (session('errors.password')) : ?>is-invalid<?php endif ?>" placeholder="<?=lang('Auth.password')?>">
							<div class="invalid-feedback">
								<?= session('errors.password') ?>
							</div>
						</div>

<?php if ($config->allowRemembering): ?>
						<div class="form-check">
							<label class="form-check-label">
								<input type="checkbox" name="remember" class="form-check-input" <?php if (old('remember')) : ?> checked <?php endif ?>>
								<?=lang('Auth.rememberMe')?>
							</label>
						</div>
<?php endif; ?>

						<br>

						<button id="login-button" type="submit" class="btn btn-lg btn-primary g-recaptcha" data-sitekey="<?php print env('recaptcha.sitekey'); ?>" data-callback='onSubmit' data-action='submit'><?= lang('Auth.loginAction') ?></button>

					</form>

					<hr>

<?php if ($config->allowRegistration) : ?>
					<p><a href="<?= url_to('register') ?>"><?=lang('Auth.needAnAccount')?></a></p>
<?php endif; ?>
<?php if ($config->activeResetter): ?>
					<p><a href="<?= url_to('forgot') ?>"><?=lang('Auth.forgotYourPassword')?></a></p>
<?php endif; ?>
				</div>
			</div>

		</div>
	</div>
</div>

<script type="text/javascript">
	function onSubmit(token) {
		document.getElementById("login-button").disabled = true;
		document.getElementById("login-button").innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <?= lang('Auth.loggingIn') ?>';
		document.getElementById("login-form").submit();
	}
	function togglePasswordVisibility() {
		var passwordInput = document.getElementById("password");
		if (passwordInput.type === "password") {
		passwordInput.type = "text";
		document.getElementById("password-toggle-icon").classList.remove("fa-eye");
		document.getElementById("password-toggle-icon").classList.add("fa-eye-slash");
		} else {
		passwordInput.type = "password";
		document.getElementById("password-toggle-icon").classList.remove("fa-eye-slash");
		document.getElementById("password-toggle-icon").classList.add("fa-eye");
		}
	}
</script>

<?= $this->endSection() ?>

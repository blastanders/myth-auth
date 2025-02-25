<?= $this->extend($config->viewLayout) ?>
<?= $this->section('main') ?>
<div class="container">
	<div class="row">
		<div class="col-sm-6 offset-sm-3">

			<div class="card">
				<h2 class="card-header">Two Factor Authentication</h2>
				<div class="card-body">

					<?= view('Myth\Auth\Views\_message_block') ?>

					<form id="tfa_form" action="<?= url_to('tfa') ?>" method="post">
						<?= csrf_field() ?>
						<?php if (!empty($recipient)) { ?>
                            <?php if (stripos($recipient, 'error') !== false) { ?>
                            <div
                                class="alert alert-danger"
                                role="alert"
                            > <?php echo $recipient; ?>
                            </div>
                            <?php } else { ?>
                                <div
                                    class="alert alert-success"
                                    role="alert"
                                > A two-factor authentication code has been sent to <?php echo $recipient; ?>. 
                                <br>
                                The code will expire after 15 mins.
                                </div>
                                <label for="tfa" class="form-label">Enter the code received to login.</label>
                            <?php } ?>
                        <?php } else { ?>
                            <label for="tfa" class="form-label">Enter the rolling code in your authenticator app to login.</label>
                        <?php } ?>
						<div class="row">
							<div class="col">
								<div class="">
									<div class="tfa_container">
										<input type="text" class="form-control text-center" name="tfa" id="tfa" aria-describedby="Multifactor authenticator" placeholder="123456" maxlength="6">
									</div>
								</div>
							</div>
							<?php if ($trust_days > 0) { ?>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="trust_this_device" id="trust_this_device">
                                    <label class="form-check-label" for="trust_this_device">Trust this device (No need for authenticator for <?= $trust_days ?> days)</label>
                                </div>
                            </div>
                            <?php } ?>
							<div class="col">
								<button type="submit" class="btn btn-primary btn-block px-5 py-2 mb-5">Login</button>
							</div>
						</div>
					</form>
				</div>
			</div>

		</div>
	</div>
</div>
<script type="text/javascript">
    //ajax the confirm_tfa_form to check if the code is correct
    $(document).ready(function(){
		$("#tfa").focus();

        $('#tfa_form').submit(function(e){
            e.preventDefault();
            var tfa = $('#tfa').val();
			var trust_this_device = 0;
			if ($('#trust_this_device').length > 0) {
				trust_this_device = $('#trust_this_device').prop('checked');
			}

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: {
                    tfa: tfa,
					trust_this_device: trust_this_device
                },
                success: function(data){
                    if(data == 'success'){
                        window.location.href = '/dashboard';
                    }else{
                        toastr.error('Incorrect code, please try again.');
                    }
                }
            });
        });
    });
</script>

<?= $this->endSection() ?>


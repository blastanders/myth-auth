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
						<label for="" class="form-label">Enter the rolling code in your authenticator app to login.</label>
						<div class="row">
							<div class="col">
								<div class="">
									<div class="tfa_container">
										<input type="text" class="form-control text-center" name="tfa" id="tfa" aria-describedby="Multifactor authenticator" placeholder="123456" maxlength="6">
									</div>
								</div>
							</div>
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
        $('#tfa_form').submit(function(e){
            e.preventDefault();
            var tfa = $('#tfa').val();
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: {
                    tfa: tfa
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


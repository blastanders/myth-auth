<?= $this->extend($config->viewLayout) ?>
<?= $this->section('main') ?>

<div class="container">
    <div class="row">
        <div class="col-sm-6 offset-sm-3">

            <div class="card">
                <h2 class="card-header">Two Factor Authentication Setup</h2>
                <div class="card-body">

                    <?= view('Myth\Auth\Views\_message_block') ?>

                    <form id="confirm_tfa_form" action="<?= url_to('tfa_setup') ?>" method="post">
                        <h1 class="display-5">Step 1</h1>
                        <hr>
                        <?= csrf_field() ?>
                        <p>Scan this QR code with your authenticator app.</p>
                        <div id="tfa_qr" class="text-center">
                            <img src="<?= $secret_qr ?>" alt="QR Code">
                        </div>
                        <h2>Or</h2>
                        <p>Manually enter the infomation</p>
                        <div id="tfa_manual">
                            <div>
                                <label>Account Name: </label>
                                <h4 id="tfa_account_name" class="text-center display-5" style='font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;'>
                                    <?= $account_name ?>
                                </h4>
                            </div>
                            <div>
                                <label for="secret">Secret (enter without spaces) <small>All O are letter O</small>: </label>
                                <h4 id="tfa_secret" class="text-center display-5" style='font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;'>
                                    <?= $formated_secret ?>
                                </h4>
                            </div>
                        </div>


                        <h1 class="mt-5 display-5">Step 2</h1>
                        <hr>

                        <div class="mb-3">
                            <label for="" class="form-label">Enter the rolling code in your authenticator app to verify everything is setup correctly.</label>
                            <div class="row justify-content-md-center">
                                <div class="tfa_confirm_container">
                                    <input type="text" class="form-control text-center" name="tfa_confirm" id="tfa_confirm" aria-describedby="Multifactor authenticator confirmation" placeholder="123456" maxlength="6">
                                    <input type="hidden" class="d-none" value="<?= $secret ?>" name="secret" id="secret">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block float-end px-5 py-2 mb-5">Verify</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
<script type="text/javascript">
    //ajax the confirm_tfa_form to check if the code is correct
    $(document).ready(function() {
        $('#confirm_tfa_form').submit(function(e) {
            e.preventDefault();
            var tfa_confirm = $('#tfa_confirm').val();
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $('#confirm_tfa_form').serialize(),
                success: function(data) {
                    if (data == 'success') {
                        window.location.href = '/dashboard';
                    } else {
                        toastr.error('Incorrect code, please try again.');
                    }
                }
            });
        });
    });
</script>

<?= $this->endSection() ?>
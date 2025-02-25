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
                        <?= csrf_field() ?>
                        <h1 class="display-5">Step 1</h1>
                        <hr>
                        <?php if ($tfa_method == 'authenticator'): ?>
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
                        <?php elseif ($tfa_method == 'email'): ?>
                            <div class="row justify-content-md-center">
                                <div class='mt-3'>
                                    Please confirm you can access the email below. If not, enter a different email address to receive the two-factor authentication code
                                </div>
                                <div class="mt-3 justify-content-md-center text-center">
                                    <input autofocus type="email" class="form-control text-center" name="tfa_recipient" id="tfa_recipient" aria-describedby="Multifactor authenticator confirmation" placeholder="john@sample.com" value="<?php echo @$tfa_recipient?>">
                                </div>
                            </div>
                            <div class="row justify-content-md-center">
                                <button type="button" class="btn btn-default btn-block px-5 py-2 mb-5" onclick="send_code('email', this);" style="background-color: white;">Send code</button>
                            </div>
                        <?php elseif ($tfa_method == 'sms'): ?>
                            <div class="row justify-content-md-center">
                                <div class='mt-3'>
                                    Please confirm you can access the number below. If not, enter a different mobile to receive the two-factor authentication code.
                                    <p>
                                        Format: +61433222111
                                    </p>
                                </div>
                                <div class="mt-3 justify-content-md-center text-center">
                                    <input autofocus type="text" class="form-control text-center" name="tfa_recipient" id="tfa_recipient" aria-describedby="Multifactor authenticator confirmation" placeholder="+61433222111" pattern="\+614[0-9]{8}" value="<?php echo @$tfa_recipient?>">
                                </div>
                                <div class="row justify-content-md-center">
                                    <button type="button" class="btn btn-default btn-block px-5 py-2 mb-5" onclick="send_code('sms', this);" style="background-color: white;">Send code</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">Unknown MFA method</div>
                        <?php endif; ?>

                        <div id="step2" class='<?php echo $tfa_method == 'authenticator' ? '' : 'd-none' ?>'>


                        <h1 class="mt-5 display-5">Step 2</h1>
                        <hr>

                        <div class="mb-3">
                            <label for="" class="form-label">Enter the rolling code in your authenticator app to verify everything is setup correctly.</label>
                            <div class="row justify-content-md-center">
                                <div class="tfa_confirm_container">
                                    <input type="text" class="form-control text-center" name="tfa_confirm" id="tfa_confirm" aria-describedby="Multifactor authenticator confirmation" placeholder="123456" maxlength="6" autofocus>
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

            toastr.error(data);

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                dataType: 'html',
                contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
                data: $("#confirm_tfa_form").serialize(),
            })
            .done(function(data) {
                if (data == 'success') {
                    window.location.href = '/dashboard';
                } else {
                    toastr.error(data);
                }
            })
            .fail(function(xhr, textStatus, errorThrown) {
                console.error(textStatus);
                console.error(xhr.responseText);
            })
            .always(function() {
                console.log('complete');
            });
        });
    });

    const send_code = (method, btn_ele) => {
        let recipient = $(`#tfa_recipient`).val();
        if (method == 'sms') {
            aus_mobile_number_regex = /^\+614\d{8}$/; // Matches +614 followed by exactly 8 digits
            if (!aus_mobile_number_regex.test(recipient.trim())) {
                toastr.error(`Invalid mobile format`);
                return false;
            }
        }

        if (method == 'email') {
            email_regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!email_regex.test(recipient.trim())) {
                toastr.error(`Invalid email format`);
                return false;
            }
        }
        $.ajax({
            url: '/send_tfa_setup_code',
            type: 'GET',
            dataType: 'json',
            data: {
                method: method,
                recipient: recipient
            },
        })
        .done(function(res) {
            if (res.status == 'ok') {
                toastr.success(res.message);
                $(`#step2`).removeClass(`d-none`);
                $(`.auth_area`).css('height', 'auto');
            } else {
                toastr.error(res.message);
            }
        })
        .fail(function() {
            toastr.error(`Unknown error`);
        })
        .always(function() {
            console.log("complete");
        });
    }
</script>

<?= $this->endSection() ?>
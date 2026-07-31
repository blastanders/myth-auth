/**
 * Session re-login modal.
 * Handles jQuery $.ajax 401 session_expired and wraps fetch().
 */
(function () {
    "use strict";
  
    var cfg = window.MythAuthSessionRelogin || {};
    var modalShown = false;
  
    function getCookie(name) {
      var m = document.cookie.match(
        new RegExp(
          "(?:^|; )" +
            name.replace(/([.$?*|{}()[\]\\/+^])/g, "\\$1") +
            "=([^;]*)",
        ),
      );
      return m ? decodeURIComponent(m[1]) : "";
    }
  
    function csrfHeaders() {
      var h = {};
      if (cfg.csrfHeader && cfg.csrfCookie) {
        var tok = getCookie(cfg.csrfCookie);
        if (tok) {
          h[cfg.csrfHeader] = tok;
        }
      }
      return h;
    }
  
    function ensureModal() {
      if (document.getElementById("myth-auth-session-modal")) {
        return;
      }
      var html = `
        <div class="modal fade" id="myth-auth-session-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 9999;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">You are logged out</h5>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Please log in again to continue.</p>
                        <div id="myth-auth-session-msg" class="alert py-2 small d-none" style="white-space: pre-line;"></div>
                        <div id="relogin-step-1">
                            <div class="mb-2">
                                <label class="form-label" for="myth-auth-session-login">Email or username</label>
                                <input type="text" class="form-control bg-light p-2" id="myth-auth-session-login" autocomplete="username" />
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="myth-auth-session-password">Password</label>
                                <input type="password" class="form-control bg-light p-2" id="myth-auth-session-password" autocomplete="current-password" />
                            </div>
                        </div>
                        <div id="relogin-step-2">
                            <div class="mb-2" id="myth-auth-session-tfa-wrap">
                                <label class="form-label" for="myth-auth-session-tfa">Authentication code</label>
                                <input type="text" class="form-control bg-light p-2" id="myth-auth-session-tfa" autocomplete="one-time-code" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-secondary" id="myth-auth-session-cancel">
                            Cancel
                        </button>

                        <button type="button" class="btn btn-primary" id="myth-auth-session-submit">
                            Log in
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
        document.getElementById('myth-auth-session-submit').addEventListener('click', submitLogin);
        document.getElementById('myth-auth-session-cancel').addEventListener('click', function () { window.location.href = '/logout';});
    }
  
    function showMsg(message, type = 'danger') {
        var el = document.getElementById('myth-auth-session-msg');
      if (!el) {
        return;
      }
  
      // reset the value of myth-auth-session-tfa
        var tfa = document.getElementById('myth-auth-session-tfa');
      if (tfa) {
            tfa.value = '';
        }

        var text = '';

        if (typeof message === 'object' && message !== null) {
            text = Object.values(message).join('\n');
        } else if (typeof message === 'string') {
            text = message;
      }

      if (text) {
        el.textContent = text;
            el.classList.remove('d-none', 'alert-danger', 'alert-success', 'alert-info', 'alert-warning');
            el.classList.add('alert-' + type);
        // console.log('showMsg', el.classList);
      } else {
            el.textContent = '';
            el.classList.remove('alert-danger', 'alert-success', 'alert-info', 'alert-warning');
            el.classList.add('d-none');
      }
    }
  
    function hideModal() {
      modalShown = false;
      var el = document.getElementById("myth-auth-session-modal");
      if (!el || typeof bootstrap === "undefined") {
        return;
      }

        Array.from(document.body.children).forEach(function (child) {
            child.style.filter = '';
            child.style.transition = '';
        });

      var inst = bootstrap.Modal.getInstance(el);
      if (inst) {
        inst.hide();
      }
      var modalBackdrop = document.querySelector(".modal-backdrop.fade.show");
      if (modalBackdrop) {
        modalBackdrop.style.removeProperty("z-index");
      }
    }
  
    function openSessionModal() {
      if (modalShown) {
        return false;
      }
      modalShown = true;
      ensureModal();
      showMsg("", "info");
      var modalBackdrop = document.querySelector(".modal-backdrop.fade.show");
      if (modalBackdrop) {
        modalBackdrop.style.zIndex = "9998";
      }
  
        document.getElementById('relogin-step-1').classList.remove('d-none');
        document.getElementById('relogin-step-2').classList.add('d-none');

        Array.from(document.body.children).forEach(function (child) {
            if (child.id !== 'myth-auth-session-modal') {
                child.style.filter = 'blur(6px)';
                child.style.transition = 'filter .2s ease';
            }
        });
  
      var el = document.getElementById("myth-auth-session-modal");
      if (typeof bootstrap !== "undefined") {
        bootstrap.Modal.getOrCreateInstance(el).show();
      }
    }
  
    function postJson(url, body) {
      var headers = Object.assign({ Accept: "application/json" }, csrfHeaders());
      return new Promise(function (resolve) {
        window.jQuery.ajax({
          url: url,
          type: "POST",
          dataType: "json",
          // Use default x-www-form-urlencoded payload for stricter servers/WAFs.
          data: body,
          headers: headers,
          success: function (res, textStatus, xhr) {
            resolve({
              ok: true,
              status: xhr && xhr.status ? xhr.status : 200,
              body: res || {},
            });
          },
          error: function (xhr) {
            var parsed = xhr && xhr.responseJSON ? xhr.responseJSON : {};
            if (
              (!parsed || typeof parsed !== "object") &&
              xhr &&
              xhr.responseText
            ) {
              try {
                parsed = JSON.parse(xhr.responseText);
              } catch (e) {
                parsed = {};
              }
            }
            resolve({
              ok: false,
              status: xhr && xhr.status ? xhr.status : 0,
              body: parsed || {},
            });
          },
        });
      });
    }
  
    function submitLogin() {
      var login =
        (document.getElementById("myth-auth-session-login") || {}).value || "";
      var password =
        (document.getElementById("myth-auth-session-password") || {}).value || "";
      var tfa =
        (document.getElementById("myth-auth-session-tfa") || {}).value || "";
      showMsg("", "info");
  
      var tfaWrap = document.getElementById("relogin-step-2");
      var useTfa = tfaWrap && !tfaWrap.classList.contains("d-none");
  
      if (useTfa && cfg.verifyTfaUrl) {
        postJson(cfg.verifyTfaUrl, { tfa: tfa, trust_this_device: "false" }).then(
          function (res) {
            if (res.body && res.body.ok) {
              hideModal();
              if (window.jQuery) {
                window.jQuery(document).trigger("myth-auth:session-restored");
              }
              return;
            }
            showMsg((res.body && res.body.message) || "Login failed", "danger");
          },
        );
        return;
      }
  
      postJson(cfg.attemptUrl, {
        login: login,
        password: password,
        remember: false,
      }).then(function (res) {
        var b = res.body || {};
        if (b.ok) {
          hideModal();
          if (window.jQuery) {
            window.jQuery(document).trigger("myth-auth:session-restored");
          }
          return;
        }
        if (b.needs_tfa) {
          if (tfaWrap) {
            tfaWrap.classList.remove("d-none");
            document.getElementById("relogin-step-1").classList.add("d-none");
          }
          // showMsg('Enter your two-factor code.', 'info');
          return;
        }
        if (b.needs_tfa_setup) {
          showMsg(
            "Complete two-factor setup in a new tab: " + (b.tfa_setup_url || ""),
            "info",
          );
          return;
        }
        if (b.needs_password_reset) {
          showMsg(
            "Password reset required. Open: " + (b.reset_url || ""),
            "info",
          );
          return;
        }
        showMsg(b.message || "Login failed", "danger");
      });
    }
  
    function bob () {
      fetch("/auth/bob").then(function (response) {
        if (response.status == 200 && response.ok) {
          hideModal();
        }
      });
    }
  
    if (window.jQuery) {
      window.jQuery(document).ajaxError(function (event, jqXHR) {
        if (jqXHR.status !== 401) {
          return;
        }
        var json = jqXHR.responseJSON;
        if (!json && jqXHR.responseText) {
          try {
            json = JSON.parse(jqXHR.responseText);
          } catch (e) {
            json = null;
          }
        }
        if (json && json.session_expired) {
          openSessionModal();
        }
      });
    }
  
    if (typeof window.fetch === "function") {
      var origFetch = window.fetch;
  
      window.fetch = function (input, init) {
        return origFetch.call(this, input, init).then(function (response) {
          if (response.status === 401) {
            response
              .clone()
              .json()
              .then(function (body) {
                if (body && body.session_expired) {
                  openSessionModal();
                }
              })
              .catch(function () {});
          }
          console.log('response', response);
          return response;
        });
      };
      // ping auth/bob every 5 mins
      //   dismiss  myth-auth-session-modal if getting {ok: true}
      setInterval(bob,5 * 60 * 1000);
      bob();
  
      document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
          
        } else {
          bob();
        }
      });
    }
  })();
  
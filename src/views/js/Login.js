/**
 * Initializes register form on login page.
 */
function initRegisterForm() {
  let registerBtnClicked = function () {
    let form = document.getElementById('pl-register-form');
    form.style.display = 'block';

    let closeBtn = document.getElementById('pl-register-form-close-btn');
    closeBtn.addEventListener('click', function () {
      form.style.display = 'none';
    })
  };

  let btn = document.getElementById('pl-register-btn');
  btn.addEventListener('click', registerBtnClicked, true);
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-validate-register]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const password = form.querySelector('[name=password]');
      const confirm = form.querySelector('[name=confirm_password]');
      const phone = form.querySelector('[name=phone]');
      let valid = true;
      if (password.value.length < 8 || password.value !== confirm.value) {
        confirm.setCustomValidity('Passwords must match and be at least 8 characters.'); valid = false;
      } else confirm.setCustomValidity('');
      if (!/^\+?8801\d{9}$|^01\d{9}$/.test(phone.value.replace(/[\s-]/g, ''))) {
        phone.setCustomValidity('Enter a valid Bangladeshi mobile number.'); valid = false;
      } else phone.setCustomValidity('');
      if (!valid || !form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
      form.classList.add('was-validated');
    });
  });
});

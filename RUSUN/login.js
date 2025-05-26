document.addEventListener('DOMContentLoaded', function () {
  const phoneInput = document.getElementById('phoneInput');
  const form = document.getElementById('loginForm');

  // Digit Only
  phoneInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault(); // Stop normal form submission

    const rawPhone = phoneInput.value.trim(); // Get the phone number and remove leading/trailing spaces
    if (!rawPhone) {
      alert('Please enter a phone number.');
      return;
    }

    //Mengirimkat FETCH API untuk permintaa HTTP ke server
    fetch('check_phone.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'phone=' + encodeURIComponent(rawPhone), // Use rawPhone directly
    })
      .then((res) => res.text())
      .then((data) => {
        //Dapatkan pesan dan peran
        const parts = data.trim().split('|');

        if (parts[0] === 'OK') {
          const role = parts[1];
          localStorage.setItem('userRole',role);
          window.location.href = 'OTP.html';
        } else {
          alert(data); // Show error message from server
        }
      })
      .catch((err) => {
        console.error(err);
        alert('Something went wrong connecting to the server.');
      });
  });
});
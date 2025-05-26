document.addEventListener('DOMContentLoaded', function() {
  const otpBoxes = document.querySelectorAll('.otp-box');
  const verifyButton = document.getElementById('verifyButton');
  const sendOtpButton = document.getElementById('sendOtpButton');

  otpBoxes[0].focus();

  otpBoxes.forEach((box) => {
    box.addEventListener('input', function() {
      this.value = this.value.replace(/\D/g, '');

      if (this.value.length === 1) {
        const nextIndex = parseInt(this.dataset.index) + 1;
        if (nextIndex < otpBoxes.length) {
          otpBoxes[nextIndex].focus();
        }
      }
      checkOTP();
    });

    box.addEventListener('keydown', function(e) {
      if (e.key === 'Backspace' && this.value.length === 0) {
        const prevIndex = parseInt(this.dataset.index) - 1;
        if (prevIndex >= 0) {
          otpBoxes[prevIndex].focus();
          otpBoxes[prevIndex].value = '';
          checkOTP();
        }
      }
    });
  });

  function checkOTP() {
    const allFilled = Array.from(otpBoxes).every(box => box.value.length === 1);
    verifyButton.disabled = !allFilled;
  }

  verifyButton.addEventListener('click', function() {
    if (!this.disabled) {
        const otpCode = Array.from(otpBoxes).map(box => box.value).join('');

        fetch('verify_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'otp=' + encodeURIComponent(otpCode),
        })
        .then(response => response.text())
        .then(data => {
            const parts = data.trim().split('|');
            if (parts[0] === 'MATCH') {
                alert('OTP verified successfully!');
                const userId = parts[1];
                localStorage.setItem('loggedInUserId', userId);

                const userRole = localStorage.getItem('userRole');

                switch (userRole) {
                    case 'Admin':
                        window.location.href = 'HOME_Admin.html';
                        break;
                    case 'Pengelola':
                        window.location.href = 'HOME_Pengelola.html';
                        break;
                    case 'Pemilik':
                    default:
                        window.location.href = 'HOME.html';
                        break;
                }
                localStorage.removeItem('userRole');
            } else {
                alert('Invalid OTP. Please try again.');
            }
        })
        .catch((error) => {
            console.error('Error during OTP verification:', error);
            alert('An error occurred during OTP verification.');
        });
      }
  });

  sendOtpButton.addEventListener('click', function() {
    fetch('generate_otp.php')  // We'll create this file next
        .then(response => response.text())
        .then(otp => {
            alert(`Your OTP code is: ${otp}`);
        })
        .catch(error => {
            console.error('Error generating OTP:', error);
            alert('Failed to generate OTP. Please try again.');
        });
  });
});
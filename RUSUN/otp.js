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
          
          console.log("Submitting OTP:", otpCode); // Debugging

          fetch('verify_otp.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: 'otp=' + encodeURIComponent(otpCode),
          })
          .then(response => {
              if (!response.ok) {
                  throw new Error('Network response was not ok');
              }
              return response.text();
          })
          .then(data => {
              console.log("Server response:", data); // Debugging
              const parts = data.trim().split('|');
              
              if (parts[0] === 'MATCH') {
                  const userId = parts[1];
                  localStorage.setItem('loggedInUserId', userId);
                  const userRole = localStorage.getItem('userRole');

                  // Redirect berdasarkan role
                  const redirects = {
                      'Admin': 'HOME_Admin.html',
                      'Pengelola': 'HOME_Pengelola.html',
                      'Pemilik': 'HOME.html'
                  };
                  
                  window.location.href = redirects[userRole] || 'HOME.html';
              } else {
                  alert('Invalid OTP. Server message: ' + (parts[1] || 'No additional info'));
                  // Reset OTP boxes
                  otpBoxes.forEach(box => box.value = '');
                  otpBoxes[0].focus();
              }
          })
          .catch((error) => {
              console.error('Error during OTP verification:', error);
              alert('Error during verification. Check console for details.');
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
})
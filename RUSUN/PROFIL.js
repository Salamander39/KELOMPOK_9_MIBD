document.addEventListener('DOMContentLoaded', function() {
    //fetch profile data
    function fetchProfileData() {
        fetch('get_profile.php')
            .then(response => {
                if (!response.ok) {
                    if (response.status === 403) {
                        throw new Error('User not authenticated. Please log in.');
                    }
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    const profileData = data.data;
                    document.querySelector('.profile-details .detail-row:nth-child(1) .value').textContent = profileData.nama;
                    document.querySelector('.profile-details .detail-row:nth-child(2) .value').textContent = profileData.NIK;
                    document.querySelector('.profile-details .detail-row:nth-child(3) .value').textContent = profileData.noPonsel;
                    document.querySelector('.profile-details .detail-row:nth-child(4) .value').textContent = profileData.alamat;
                    document.querySelector('.profile-details .detail-row:nth-child(5) .value').textContent = profileData.idPengguna;
                    document.querySelector('.profile-details .detail-row:nth-child(6) .value').textContent = '******'; // Password should not be displayed
                } else {
                    console.error('Error fetching profile:', data.message);
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
                alert('Failed to load profile data: ' + error.message);
            });
    }

    //fetch profile data when the page loads
    fetchProfileData();

    // New: Password Change Modal Logic
    const settingsButton = document.querySelector('.settings-button');
    const passwordChangeModal = document.getElementById('passwordChangeModal');
    const closeButton = document.querySelector('.close-button');
    const passwordChangeForm = document.getElementById('passwordChangeForm');
    const oldPasswordInput = document.getElementById('oldPassword');
    const newPasswordInput = document.getElementById('newPassword');
    const confirmNewPasswordInput = document.getElementById('confirmNewPassword');
    const passwordMessage = document.getElementById('passwordMessage');

    // Show modal
    settingsButton.addEventListener('click', function() {
        passwordChangeModal.style.display = 'flex'; // flex to center
        passwordMessage.textContent = ''; // Clear previous messages
        passwordMessage.className = 'message'; // Reset message class
        passwordChangeForm.reset(); // Clear form fields
    });

    // Hide modal when clicking on <span> (x)
    closeButton.addEventListener('click', function() {
        passwordChangeModal.style.display = 'none';
    });

    // Hide modal when clicking outside of the modal content
    window.addEventListener('click', function(event) {
        if (event.target === passwordChangeModal) {
            passwordChangeModal.style.display = 'none';
        }
    });

    // Handle password change form submission
    passwordChangeForm.addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent default form submission

        const oldPassword = oldPasswordInput.value;
        const newPassword = newPasswordInput.value;
        const confirmNewPassword = confirmNewPasswordInput.value;

        // Basic client-side validation
        if (newPassword !== confirmNewPassword) {
            passwordMessage.textContent = 'Password baru dan konfirmasi password tidak cocok!';
            passwordMessage.className = 'message error';
            return;
        }

        if (newPassword.length < 5) { // MIN pass length
            passwordMessage.textContent = 'Password baru minimal 5 karakter.';
            passwordMessage.className = 'message error';
            return;
        }

        // Send data to PHP script
        fetch('update_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                oldPassword: oldPassword,
                newPassword: newPassword
            })
        })
        .then(response => {
            if (!response.ok) {
                // If the response is not OK, try to read the error message from the body
                return response.json().then(err => { throw new Error(err.message || 'Network response was not ok.'); });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                passwordMessage.textContent = 'Password berhasil diubah!';
                passwordMessage.className = 'message success';
                // Optionally close modal after a delay
                setTimeout(() => {
                    passwordChangeModal.style.display = 'none';
                    passwordChangeForm.reset();
                }, 2000); 
            } else {
                passwordMessage.textContent = data.message || 'Gagal mengubah password.';
                passwordMessage.className = 'message error';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            passwordMessage.textContent = 'Terjadi kesalahan: ' + error.message;
            passwordMessage.className = 'message error';
        });
    });
});
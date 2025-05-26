document.addEventListener('DOMContentLoaded', function() {
    // Function to fetch profile data
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

    // Call the function to fetch profile data when the page loads
    fetchProfileData();
});
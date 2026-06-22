

async function postData(url, data) {
    const formData = new FormData();
    Object.keys(data).forEach(key => formData.append(key, data[key]));

    const response = await fetch(url, {
        method: 'POST',
        body: formData
    });
    return response.json();
}

// Helper pour GET
async function getData(url) {
    const res = await fetch(url);
    return res.json();
}

// CSRF Token (si besoin)
function getCsrfToken() {
    return document.querySelector('input[name="csrf_token"]')?.value;
}
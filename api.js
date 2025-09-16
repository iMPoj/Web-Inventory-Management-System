 const API_URL = 'api.php';

export async function fetchData(action) {
    try {
        const url = new URL(API_URL, window.location.href);
        url.searchParams.set('action', action);
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success === false) throw new Error(result.message);
        return result;
    } catch (error) {
        console.error(`Could not fetch ${action}:`, error);
        return []; 
    }
}

export async function postData(action, data) {
     try {
        const formData = new FormData();
        formData.append('action', action);

        // Handle both plain objects and FormData objects
        if (data instanceof FormData) {
            for (const [key, value] of data.entries()) {
                // If the key is not 'action', append it. This prevents duplicating the action.
                if (key !== 'action') {
                    formData.append(key, value);
                }
            }
        } else {
            for (const key in data) {
                formData.append(key, data[key]);
            }
        }
        
        const url = new URL(API_URL, window.location.href);
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const result = await response.json();
        if (result.success === false) throw new Error(result.message);
        return result;
    } catch (error) {
        console.error(`Could not post ${action}:`, error);
        return { success: false, message: error.message };
    }

<template>
    <div class="login-wrapper">
        <div class="login-container">
            <h3>Sign in to {{ siteTitle }}</h3>

            <form @submit.prevent="handleSubmit">
                <!-- Username/Email Field -->
                <div class="field mb-4">
                    <label for="username" class="font-semibold"
                        >Username or Email</label
                    >
                    <InputText
                        id="username"
                        v-model="formData.username"
                        placeholder="Enter your username or email"
                        :class="{ 'p-invalid': errors.username }"
                        autocomplete="username"
                        autofocus
                    />
                    <small v-if="errors.username" class="p-error">{{
                        errors.username
                    }}</small>
                </div>

                <!-- Password Field -->
                <div class="field mb-4">
                    <label for="password" class="font-semibold">Password</label>
                    <Password
                        id="password"
                        v-model="formData.password"
                        placeholder="Enter your password"
                        :class="{ 'p-invalid': errors.password }"
                        :feedback="false"
                        toggleMask
                        inputClass="password-input"
                        autocomplete="current-password"
                    />
                    <small v-if="errors.password" class="p-error">{{
                        errors.password
                    }}</small>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="field-checkbox mb-4">
                    <Checkbox
                        id="remember"
                        v-model="formData.remember"
                        :binary="true"
                    />
                    <label for="remember" class="ml-2">Remember me</label>
                </div>

                <!-- Timezone Hidden Field -->
                <input type="hidden" :value="formData.timezone" />

                <!-- Login Button -->
                <Button
                    type="submit"
                    :loading="loading"
                    :disabled="loading"
                    class="login-button mb-3"
                    severity="primary"
                >
                    <span v-if="!loading">Login</span>
                    <span v-else>Signing in...</span>
                </Button>

                <!-- Forgot Password Link -->
                <div class="text-center mb-3">
                    <span class="text-sm text-600">Forgot your password? </span>
                    <a
                        href="#"
                        class="text-sm text-primary font-semibold no-underline"
                        >Reset here</a
                    >
                </div>

                <!-- Google Login Button -->
                <a :href="googleAuthUrl" class="google-login-btn">
                    <img
                        src="https://developers.google.com/identity/images/g-logo.png"
                        alt="Google logo"
                    />
                    <span>Continue with Google</span>
                </a>
            </form>
        </div>

        <!-- Footer -->
        <footer class="login-footer">
            <slot name="footer">
                © {{ currentYear }} IMS. All rights reserved.
            </slot>
        </footer>

        <!-- Audio Elements (sources loaded dynamically in onMounted to avoid Vite import issues) -->
        <audio ref="logoutAudio" style="display: none"></audio>
        <audio ref="errorAudio" style="display: none"></audio>
    </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from "vue";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import Checkbox from "primevue/checkbox";

export default {
    name: "Login",
    components: {
        InputText,
        Password,
        Button,
        Checkbox,
    },
    props: {
        systemDesign: {
            type: Object,
            default: () => ({}),
        },
    },
    setup(props) {
        // Refs
        const loading = ref(false);
        const logoutAudio = ref(null);
        const errorAudio = ref(null);

        // Form Data
        const formData = reactive({
            username: "",
            password: "",
            remember: false,
            timezone: "",
        });

        // Errors
        const errors = reactive({
            username: "",
            password: "",
        });

        // Computed
        const currentYear = computed(() => new Date().getFullYear());
        const siteTitle = computed(
            () => props.systemDesign?.site_title || "IMS"
        );
        const googleAuthUrl = computed(() => "/auth/google");

        // Methods
        const playAudio = (audioElement, audioName) => {
            if (!audioElement) {
                console.log(`Audio element not available: ${audioName}`);
                return;
            }

            // Check if audio can be loaded
            if (audioElement.error) {
                console.log(
                    `Audio file not found or cannot be loaded: ${audioName}`
                );
                return;
            }

            console.log(`Attempting to play ${audioName} audio`);
            audioElement.currentTime = 0;

            const playPromise = audioElement.play();

            if (playPromise !== undefined) {
                playPromise
                    .then(() =>
                        console.log(`${audioName} audio played successfully`)
                    )
                    .catch((error) => {
                        console.log(
                            `${audioName} audio playback failed (this is optional):`,
                            error.message
                        );
                    });
            }
        };

        const clearErrors = () => {
            errors.username = "";
            errors.password = "";
        };

        const clearInputErrors = () => {
            Object.keys(errors).forEach((key) => {
                errors[key] = "";
            });
        };

        const showToast = (severity, summary, detail, life = 5000) => {
            // Use SweetAlert2 if available (as per your app.js)
            if (window.Swal) {
                const icon =
                    severity === "error"
                        ? "error"
                        : severity === "success"
                        ? "success"
                        : "info";
                window.Swal.fire({
                    icon: icon,
                    title: summary,
                    text: detail,
                    timer: life,
                    showConfirmButton: severity === "error",
                    toast: true,
                    position: "top-end",
                });
            } else {
                console.log(`${severity}: ${summary} - ${detail}`);
            }
        };

        const validateForm = () => {
            clearErrors();
            let isValid = true;

            if (!formData.username.trim()) {
                errors.username = "Username or email is required";
                isValid = false;
            }

            if (!formData.password) {
                errors.password = "Password is required";
                isValid = false;
            }

            return isValid;
        };

        const handleSubmit = async () => {
            if (!validateForm()) {
                playAudio(errorAudio.value, "Validation Error");
                return;
            }

            loading.value = true;

            try {
                // Set timezone before submitting
                formData.timezone =
                    Intl.DateTimeFormat().resolvedOptions().timeZone;

                // Get CSRF token
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content");

                // Make POST request to /login using axios (as per your app.js)
                const response = await window.axios.post(
                    "/login",
                    {
                        username: formData.username,
                        password: formData.password,
                        remember: formData.remember ? "1" : "0",
                        timezone: formData.timezone,
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken || "",
                        },
                    }
                );

                // If we get here, the request succeeded - Laravel will redirect
                // This shouldn't normally execute because Laravel returns a redirect
                if (response.data?.redirect) {
                    window.location.href = response.data.redirect;
                } else {
                    window.location.href = "/dashboard";
                }
            } catch (error) {
                console.error("Login error:", error);
                playAudio(errorAudio.value, "Error");

                if (error.response) {
                    const status = error.response.status;
                    const data = error.response.data;

                    if (status === 422 && data.errors) {
                        // Validation errors
                        Object.keys(data.errors).forEach((key) => {
                            if (errors.hasOwnProperty(key)) {
                                errors[key] = Array.isArray(data.errors[key])
                                    ? data.errors[key][0]
                                    : data.errors[key];
                            }
                        });

                        // Show first error
                        const firstError = Object.values(data.errors)[0];
                        showToast(
                            "error",
                            "Login Failed",
                            Array.isArray(firstError)
                                ? firstError[0]
                                : firstError
                        );
                    } else if (status === 419) {
                        // CSRF token expired
                        showToast(
                            "error",
                            "Session Expired",
                            "Please refresh the page and try again."
                        );
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        // Generic error
                        showToast(
                            "error",
                            "Login Failed",
                            data.message ||
                                "Please check your credentials and try again."
                        );
                    }
                } else {
                    showToast(
                        "error",
                        "Error",
                        "An unexpected error occurred. Please try again."
                    );
                }
            } finally {
                loading.value = false;
            }
        };

        const checkForMessages = () => {
            // Check URL params for logout message
            const urlParams = new URLSearchParams(window.location.search);
            const logoutMessage = urlParams.get("logout_success");
            const errorMessage = urlParams.get("error");

            if (logoutMessage) {
                showToast(
                    "success",
                    "Logged Out",
                    decodeURIComponent(logoutMessage),
                    3000
                );

                // Clean URL
                window.history.replaceState(
                    {},
                    document.title,
                    window.location.pathname
                );
            }

            if (errorMessage) {
                playAudio(errorAudio.value, "Error");
                showToast("error", "Error", decodeURIComponent(errorMessage));

                // Clean URL
                window.history.replaceState(
                    {},
                    document.title,
                    window.location.pathname
                );
            }
        };

        // Lifecycle
        onMounted(() => {
            console.log("Login page loaded");
            checkForMessages();

            // Set timezone immediately
            formData.timezone =
                Intl.DateTimeFormat().resolvedOptions().timeZone;

            // Setup audio elements dynamically to avoid Vite import issues
            if (logoutAudio.value) {
                logoutAudio.value.innerHTML = `
          <source src="${audioSources.logout.mp3}" type="audio/mpeg">
          <source src="${audioSources.logout.wav}" type="audio/wav">
        `;
                logoutAudio.value.load();
            }

            if (errorAudio.value) {
                errorAudio.value.innerHTML = `
          <source src="${audioSources.error.mp3}" type="audio/mpeg">
          <source src="${audioSources.error.wav}" type="audio/wav">
        `;
                errorAudio.value.load();
            }

            // Check if audio files are available (optional feature)
            setTimeout(() => {
                if (logoutAudio.value && !logoutAudio.value.error) {
                    console.log("✓ Logout audio available");
                } else {
                    console.log("ℹ Logout audio not available (optional)");
                }

                if (errorAudio.value && !errorAudio.value.error) {
                    console.log("✓ Error audio available");
                } else {
                    console.log("ℹ Error audio not available (optional)");
                }
            }, 500);

            // Prevent back button after login
            window.addEventListener("pageshow", (event) => {
                if (event.persisted) {
                    window.location.reload();
                }
            });

            // Clear errors on input
            const inputs = document.querySelectorAll("input");
            inputs.forEach((input) => {
                input.addEventListener("input", clearInputErrors);
            });
        });

        return {
            loading,
            logoutAudio,
            errorAudio,
            formData,
            errors,
            currentYear,
            siteTitle,
            googleAuthUrl,
            handleSubmit,
            clearInputErrors,
        };
    },
};
</script>

<style scoped src="./Login.css"></style>

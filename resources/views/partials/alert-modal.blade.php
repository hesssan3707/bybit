{{-- Modern Alert Modal Component --}}
<div id="alertModal" class="alert-modal-overlay" style="display: none;">
    <div class="alert-modal-container">
        <div class="alert-modal-content">
            <div class="alert-modal-header">
                <div class="alert-modal-icon" id="alertModalIcon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <h3 id="alertModalTitle">اطلاع‌رسانی</h3>
                <button class="alert-modal-close" onclick="closeAlertModal('cancel')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="alert-modal-body">
                <p id="alertModalMessage">پیام شما اینجا نمایش داده می‌شود</p>
            </div>
            <div class="alert-modal-footer">
                <button id="alertModalConfirm" class="btn btn-primary" onclick="closeAlertModal('confirm')">
                    تأیید
                </button>
                <button id="alertModalCancel" class="btn btn-secondary" onclick="closeAlertModal('cancel')" style="display: none;">
                    انصراف
                </button>
                <button id="alertModalSecondaryConfirm" class="btn btn-danger" onclick="closeAlertModal('secondary')" style="display: none;">
                    اقدام اضافی
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.alert-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.alert-modal-overlay.show {
    opacity: 1;
}

.alert-modal-container {
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.7) translateY(50px);
    transition: transform 0.3s ease;
}

.alert-modal-overlay.show .alert-modal-container {
    transform: scale(1) translateY(0);
}

.alert-modal-content {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
}

.alert-modal-header {
    padding: 25px 30px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    position: relative;
}

.alert-modal-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    background: linear-gradient(135deg, #007bff, #0056b3);
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.alert-modal-icon.success {
    background: linear-gradient(135deg, #28a745, #1e7e34);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.alert-modal-icon.error {
    background: linear-gradient(135deg, #dc3545, #c82333);
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
}

.alert-modal-icon.warning {
    background: linear-gradient(135deg, #ffc107, #e0a800);
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
}

.alert-modal-header h3 {
    margin: 0;
    flex: 1;
    font-size: 20px;
    font-weight: 600;
    color: #333;
}

.alert-modal-close {
    position: absolute;
    top: 20px;
    left: 20px;
    background: none;
    border: none;
    font-size: 20px;
    color: #666;
    cursor: pointer;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.alert-modal-close:hover {
    background: rgba(0, 0, 0, 0.1);
    color: #333;
}

.alert-modal-body {
    padding: 25px 30px;
    text-align: center;
}

.alert-modal-body p {
    margin: 0;
    font-size: 16px;
    line-height: 1.6;
    color: #555;
}

.alert-modal-footer {
    padding: 20px 30px 30px;
    display: flex;
    gap: 15px;
    justify-content: center;
}

.alert-modal-footer .btn {
    padding: 12px 30px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 100px;
}

.alert-modal-footer .btn-primary {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.alert-modal-footer .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
}

.alert-modal-footer .btn-secondary {
    background: rgba(108, 117, 125, 0.1);
    color: #6c757d;
    border: 1px solid rgba(108, 117, 125, 0.3);
}

.alert-modal-footer .btn-secondary:hover {
    background: rgba(108, 117, 125, 0.2);
    transform: translateY(-1px);
}

.alert-modal-footer .btn-danger {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
}

.alert-modal-footer .btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
}

/* Animation keyframes */
@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.7) translateY(50px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes modalSlideOut {
    from {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
    to {
        opacity: 0;
        transform: scale(0.7) translateY(50px);
    }
}

/* Responsive design */
@media (max-width: 768px) {
    .alert-modal-container {
        width: 95%;
        margin: 20px;
    }
    
    .alert-modal-header,
    .alert-modal-body,
    .alert-modal-footer {
        padding-left: 20px;
        padding-right: 20px;
    }
    
    .alert-modal-footer {
        flex-direction: column;
    }
    
    .alert-modal-footer .btn {
        width: 100%;
    }
}
</style>

<script>
// Global alert modal functions
let alertModalCallback = null;

function showAlertModal(options = {}) {
    const {
        title = 'اطلاع‌رسانی',
        message = '',
        type = 'info', // info, success, error, warning
        confirmText = 'تأیید',
        cancelText = 'انصراف',
        showCancel = false,
        secondaryConfirmText = '',
        showSecondaryConfirm = false,
        onConfirm = null,
        onSecondaryConfirm = null,
        onCancel = null
    } = options;

    // Set modal content
    document.getElementById('alertModalTitle').textContent = title;
    document.getElementById('alertModalMessage').textContent = message;
    document.getElementById('alertModalConfirm').textContent = confirmText;
    document.getElementById('alertModalCancel').textContent = cancelText;
    document.getElementById('alertModalSecondaryConfirm').textContent = secondaryConfirmText || 'اقدام اضافی';

    // Set icon and styling based on type
    const iconElement = document.getElementById('alertModalIcon');
    const iconClass = iconElement.querySelector('i');
    
    // Reset classes
    iconElement.className = 'alert-modal-icon';
    iconClass.className = 'fas';
    
    switch(type) {
        case 'success':
            iconElement.classList.add('success');
            iconClass.classList.add('fa-check');
            break;
        case 'error':
            iconElement.classList.add('error');
            iconClass.classList.add('fa-exclamation-triangle');
            break;
        case 'warning':
            iconElement.classList.add('warning');
            iconClass.classList.add('fa-exclamation-triangle');
            break;
        default:
            iconClass.classList.add('fa-info-circle');
    }

    // Show/hide cancel button
    const cancelBtn = document.getElementById('alertModalCancel');
    cancelBtn.style.display = showCancel ? 'inline-block' : 'none';
    const secondaryBtn = document.getElementById('alertModalSecondaryConfirm');
    secondaryBtn.style.display = showSecondaryConfirm ? 'inline-block' : 'none';

    // Set callbacks
    alertModalCallback = {
        onConfirm: onConfirm,
        onSecondaryConfirm: onSecondaryConfirm,
        onCancel: onCancel
    };

    // Show modal
    const modal = document.getElementById('alertModal');
    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);

    // Focus on confirm button
    setTimeout(() => {
        document.getElementById('alertModalConfirm').focus();
    }, 300);
}

function closeAlertModal(which = 'confirm') {
    const modal = document.getElementById('alertModal');
    modal.classList.remove('show');
    
    setTimeout(() => {
        modal.style.display = 'none';
        
        // Execute callback if exists
        if (alertModalCallback) {
            if (which === 'confirm' && alertModalCallback.onConfirm) {
                alertModalCallback.onConfirm();
            } else if (which === 'secondary' && alertModalCallback.onSecondaryConfirm) {
                alertModalCallback.onSecondaryConfirm();
            } else if (which === 'cancel' && alertModalCallback.onCancel) {
                alertModalCallback.onCancel();
            }
            alertModalCallback = null;
        }
    }, 300);
}

// Handle confirm button click
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('alertModalConfirm').addEventListener('click', function() {
        closeAlertModal('confirm');
    });
    document.getElementById('alertModalCancel').addEventListener('click', function() {
        closeAlertModal('cancel');
    });
    document.getElementById('alertModalSecondaryConfirm').addEventListener('click', function() {
        closeAlertModal('secondary');
    });
    
    // Close modal when clicking outside
    document.getElementById('alertModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAlertModal('cancel');
        }
    });
    
    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('alertModal');
            if (modal.style.display !== 'none') {
                closeAlertModal('cancel');
            }
        }
    });
});

// Convenience functions to replace standard alerts
function modernAlert(message, type = 'info', title = null) {
    const titles = {
        'info': 'اطلاع‌رسانی',
        'success': 'موفقیت‌آمیز',
        'error': 'خطا',
        'warning': 'هشدار'
    };
    
    showAlertModal({
        title: title || titles[type],
        message: message,
        type: type
    });
}

function modernConfirm(title, message, onConfirm) {
    showAlertModal({
        title: title,
        message: message,
        type: 'warning',
        confirmText: 'تأیید',
        cancelText: 'انصراف',
        showCancel: true,
        onConfirm: onConfirm
    });
}
</script>
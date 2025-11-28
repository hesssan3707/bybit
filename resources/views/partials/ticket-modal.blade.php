<div id="ticketModal" class="ticket-modal-overlay" style="display: none;">
    <div class="ticket-modal-container">
        <div class="ticket-modal-content">
            <div class="ticket-modal-header">
                <div class="ticket-modal-title">
                    <i class="fas fa-headset"></i>
                    <h3>پشتیبانی</h3>
                </div>
                <button class="ticket-modal-close" onclick="closeTicketModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="ticket-modal-body">
                <form id="ticketForm">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">نوع درخواست</label>
                        <div class="ticket-type-selector">
                            <label class="type-option active" data-type="issue">
                                <input type="radio" name="category" value="issue" checked onchange="updateTicketType(this)">
                                <div class="type-icon">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <span class="type-text">گزارش خطا</span>
                            </label>
                            <label class="type-option" data-type="suggestion">
                                <input type="radio" name="category" value="suggestion" onchange="updateTicketType(this)">
                                <div class="type-icon">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                                <span class="type-text">پیشنهاد / سوال</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ticketTitle" class="form-label">موضوع</label>
                        <input type="text" id="ticketTitle" name="title" class="form-control" placeholder="عنوان درخواست خود را وارد کنید" required maxlength="255">
                    </div>

                    <div class="form-group">
                        <label for="ticketDescription" class="form-label">توضیحات</label>
                        <textarea id="ticketDescription" name="description" class="form-control" rows="5" placeholder="توضیحات کامل را اینجا بنویسید..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <span class="btn-text">
                            <i class="fas fa-paper-plane"></i>
                            ارسال درخواست
                        </span>
                        <span class="btn-loading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                            در حال ارسال...
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.ticket-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    padding: 20px;
}

.ticket-modal-overlay.show {
    opacity: 1;
}

.ticket-modal-container {
    width: 100%;
    max-width: 550px;
    max-height: 90vh;
    overflow-y: auto;
    overflow-x: hidden;
}

.ticket-modal-content {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(240, 240, 250, 0.95));
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    transform: scale(0.9) translateY(30px);
    transition: transform 0.3s ease;
}

.ticket-modal-overlay.show .ticket-modal-content {
    transform: scale(1) translateY(0);
}

.ticket-modal-header {
    padding: 25px 30px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid rgba(255, 255, 255, 0.2);
}

.ticket-modal-title {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
}

.ticket-modal-title i {
    font-size: 24px;
}

.ticket-modal-header h3 {
    margin: 0;
    font-size: 1.4em;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.ticket-modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    font-size: 1.2em;
    color: white;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.ticket-modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.ticket-modal-body {
    padding: 30px;
}

.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #333;
    font-size: 0.95em;
}

.form-control {
    width: 100%;
    max-width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 1em;
    transition: all 0.3s;
    background: white;
    color: #333;
    box-sizing: border-box;
}

.form-control:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-control::placeholder {
    color: #aaa;
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.ticket-type-selector {
    display: flex;
    gap: 12px;
    background: #f5f5f7;
    padding: 6px;
    border-radius: 12px;
}

.type-option {
    flex: 1;
    padding: 14px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: transparent;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
}

.type-option input {
    display: none;
}

.type-option:hover {
    background: rgba(102, 126, 234, 0.08);
}

.type-option.active {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.type-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #e8e8ea;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    font-size: 1.3em;
    transition: all 0.2s;
}

.type-option.active .type-icon {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.type-text {
    font-weight: 600;
    color: #666;
    font-size: 0.9em;
}

.type-option.active .type-text {
    color: #333;
}

.btn-submit {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.1em;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-text, .btn-loading {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Responsive */
@media (max-width: 600px) {
    .ticket-modal-container {
        max-width: 100%;
    }
    
    .ticket-modal-body {
        padding: 20px;
    }
    
    .ticket-type-selector {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function openTicketModal() {
    const modal = document.getElementById('ticketModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
    // Focus first input
    setTimeout(() => document.getElementById('ticketTitle').focus(), 400);
}

function closeTicketModal() {
    const modal = document.getElementById('ticketModal');
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}

function updateTicketType(radio) {
    document.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('active'));
    radio.closest('.type-option').classList.add('active');
}

document.getElementById('ticketForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = this.querySelector('.btn-submit');
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');
    
    btn.disabled = true;
    btnText.style.display = 'none';
    btnLoading.style.display = 'flex';
    
    const formData = new FormData(this);
    
    fetch('{{ route("tickets.store") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeTicketModal();
            modernAlert(data.message, 'success');
            this.reset();
            // Reset type selector
            document.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector('.type-option[data-type="issue"]').classList.add('active');
        } else {
            modernAlert(data.message || 'خطایی رخ داد', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        modernAlert('خطایی در برقراری ارتباط رخ داد', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btnText.style.display = 'flex';
        btnLoading.style.display = 'none';
    });
});

// Close on outside click
document.getElementById('ticketModal').addEventListener('click', function(e) {
    if (e.target === this) closeTicketModal();
});

// Close on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('ticketModal');
        if (modal.style.display === 'flex') {
            closeTicketModal();
        }
    }
});
</script>

import 'bootstrap';
import $ from 'jquery';

window.$ = window.jQuery = $;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json',
    },
});

const showAlert = (message, type = 'success') => {
    const alert = $('<div>', {
        class: `alert alert-${type} alert-dismissible fade show`,
        role: 'alert',
    }).append(
        $('<span>').text(message),
        $('<button>', {
            type: 'button',
            class: 'btn-close',
            'data-bs-dismiss': 'alert',
            'aria-label': 'Close',
        }),
    );

    $('#alert-container').empty().append(alert);
};

const createFileRow = (file) => {
    const actions = $('<td>', { class: 'text-end text-nowrap' }).append(
        $('<a>', {
            class: 'btn btn-sm btn-outline-primary me-1',
            href: file.download_url,
            text: 'Download',
        }),
        $('<button>', {
            class: 'btn btn-sm btn-outline-danger delete-file',
            type: 'button',
            'data-url': file.delete_url,
            'data-name': file.original_name,
            text: 'Delete',
        }),
    );

    return $('<tr>', { 'data-file-id': file.id }).append(
        $('<td>').append(
            $('<div>', { class: 'fw-semibold text-break' }).text(file.original_name),
            $('<small>', { class: 'text-uppercase text-secondary' }).text(file.extension),
        ),
        $('<td>').text(file.size_human),
        $('<td>').append($('<time>', { datetime: file.uploaded_at }).text(file.uploaded_at_human)),
        $('<td>').append($('<time>', { datetime: file.expires_at }).text(file.expires_at_human)),
        actions,
    );
};

$(document).on('submit', '#upload-form', function (event) {
    event.preventDefault();

    const form = this;
    const input = $('#document')[0];
    const selectedFile = input.files[0];
    const maxSizeMb = Number(input.dataset.maxSizeMb || 10);
    const errorContainer = $('#document-error');

    errorContainer.text('');

    if (!selectedFile) {
        errorContainer.text('Choose a file to upload.');
        return;
    }

    if (selectedFile.size > maxSizeMb * 1024 * 1024) {
        errorContainer.text(`The file must not exceed ${maxSizeMb} MB.`);
        return;
    }

    const formData = new FormData(form);
    const button = $('#upload-button');
    const progressWrapper = $('#upload-progress-wrapper');
    const progress = $('#upload-progress');

    button.prop('disabled', true).text('Uploading…');
    progressWrapper.removeClass('d-none');
    progress.css('width', '0%').text('0%').attr('aria-valuenow', 0);

    $.ajax({
        url: form.action,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: () => {
            const xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', (progressEvent) => {
                if (!progressEvent.lengthComputable) return;

                const percent = Math.round((progressEvent.loaded / progressEvent.total) * 100);
                progress.css('width', `${percent}%`).text(`${percent}%`).attr('aria-valuenow', percent);
            });
            return xhr;
        },
    })
        .done((response) => {
            $('#empty-state').remove();
            $('#files-table-body').prepend(createFileRow(response.data));
            form.reset();
            showAlert(`“${response.data.original_name}” was uploaded successfully.`);
        })
        .fail((xhr) => {
            const validationMessage = xhr.responseJSON?.errors?.document?.[0];
            const message = validationMessage || xhr.responseJSON?.message || 'The upload failed. Please try again.';
            errorContainer.text(message);
            showAlert(message, 'danger');
        })
        .always(() => {
            button.prop('disabled', false).text('Upload');
            setTimeout(() => progressWrapper.addClass('d-none'), 500);
        });
});
$(document).on('click', '.delete-file', function () {
    const button = $(this);
    const name = String(button.data('name'));

    if (!window.confirm(`Delete “${name}”? This action cannot be undone.`)) {
        return;
    }

    button.prop('disabled', true).text('Deleting…');

    $.ajax({
        url: button.data('url'),
        method: 'DELETE',
    })
        .done(() => {
            const row = button.closest('tr');
            row.fadeOut(180, () => {
                row.remove();

                if ($('#files-table-body tr').length === 0) {
                    $('#files-table-body').append(
                        $('<tr>', { id: 'empty-state' }).append(
                            $('<td>', {
                                colspan: 5,
                                class: 'text-center text-secondary py-5',
                                text: 'No files have been uploaded yet.',
                            }),
                        ),
                    );
                }
            });
            showAlert(`“${name}” was deleted. The notification event is queued.`);
        })
        .fail((xhr) => {
            showAlert(xhr.responseJSON?.message || 'The file could not be deleted.', 'danger');
            button.prop('disabled', false).text('Delete');
        });
});

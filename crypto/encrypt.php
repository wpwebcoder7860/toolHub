<?php
require __DIR__ . "/../common/auth.php";
$me = currentUser(); // public tool, no login required
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>encrypt</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="csrf-token" content="<?= h(csrfToken()) ?>">
    <link href="../common/assets/bootstrap.min.css" rel="stylesheet">
    <link href="../common/assets/hub.css" rel="stylesheet">
    <link rel="shortcut icon" href="/srfAddon/images/favicon.ico">
    <script src="../common/assets/sweetalert2.js"></script>
    <style>
        /* ================= GLOBAL ================= */

        html {
            height: -webkit-fill-available;
        }

        body {
            background-color: #20c9a6;
            min-height: 100vh;
            min-height: -webkit-fill-available;
            overflow-x: hidden;
            /* horizontal scroll prevent */
        }

        /* ================= EDITOR ================= */
        .form-control {
            display: block;
            width: 100%;
            padding: .375rem .75rem;
            font-size: 1rem;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: .25rem;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }

        .form-select {
            font-size: 14px !important;
        }

        .editor {
            min-height: 300px;
            min-height: 80vh;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: monospace;
            padding: 10px;
            overflow: auto;
            resize: vertical;
        }

        .project-table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
        }

        .project-table th,
        .project-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 14px !important;
        }

        .project-table th {
            background-color: #4CAF50;
            color: white;
            font-size: 14px !important;
        }

        .project-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .project-table tr:hover {
            background-color: #f1f1f1;
        }

        /* ================= TOOLBAR ================= */

        .toolbar {
            background-color: #2c3e50;
            color: #ecf0f1;
            padding: 5px 10px;
            font-weight: bold;
            border-radius: 4px 4px 0 0;
        }

        /* ================= SIDEBAR ================= */

        .sidebar {
            background-color: #20c9a6;
            padding: 15px;
            /* border-radius: 4px; */
            min-height: 300px;
            overflow-y: auto;
        }

        .sidebar button,
        .sidebar select {
            margin-bottom: 10px;
            width: 100%;
            border-radius: 0;
        }

        .custom-input {
            width: 100% !important;
            margin: 5px 0;
        }

        /* ================= LOADER ================= */

        #outputLoader {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(44, 62, 80, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #fff;
            font-size: 1.2rem;
        }

        /* ================= MOBILE (Max 576px) ================= */

        @media (max-width: 576px) {

            body {
                overflow-x: hidden;
            }

            .editor {
                min-height: 200px !important;
                max-height: none;
                font-size: 14px;
            }

            .sidebar {
                height: auto;
                padding: 10px;
            }

            .toolbar {
                font-size: 14px;
                text-align: center;
            }

            textarea {
                width: 100% !important;
            }

            .col-md-5,
            .col-md-2 {
                width: 100% !important;
                max-width: 100%;
                margin-bottom: 20px;
            }

            .d-flex {
                flex-direction: column !important;
                align-items: center !important;
            }
        }

        /* ================= SMALL TABLET (576px–768px) ================= */

        @media (min-width: 576px) and (max-width: 768px) {

            .editor {
                min-height: 250px;
                font-size: 15px;
            }

            .toolbar {
                font-size: 16px;
            }

            .col-md-5 {
                width: 100%;
                margin-bottom: 15px;
            }

            .col-md-2 {
                width: 100%;
                text-align: center;
            }

            .sidebar {
                height: auto;
            }
        }

        /* ================= TABLET (768px–992px) ================= */

        @media (min-width: 768px) and (max-width: 992px) {

            .editor {
                min-height: 300px;
                font-size: 16px;
            }

            .toolbar {
                font-size: 18px;
            }

            .col-md-5 {
                width: 48% !important;
            }

            .col-md-2 {
                width: 100%;
                margin-top: 10px;
                text-align: center;
            }

            .sidebar {
                height: auto;
            }
        }

        /* ================= LAPTOP (992px–1200px) ================= */

        @media (min-width: 992px) and (max-width: 1200px) {

            .editor {
                min-height: 350px;
                font-size: 16px;
            }

            .toolbar {
                font-size: 18px;
            }
        }

        /* ================= LARGE DESKTOP (1200px+) ================= */

        @media (min-width: 1200px) {

            .editor {
                font-size: 17px;
                /* min-height: 400px; */
                min-height: 80vh;
            }

            .toolbar {
                font-size: 14px;
                font-weight: normal;
                margin-right: -10px;
                padding-right: 4px;
            }
        }
    </style>
</head>

<body>

    <div id="outputLoader">
        <div style="width: 40%;">
            <div class="progress mb-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width: 100%"></div>
            </div>
            <div class="text-center">Please wait...</div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row mt-2 justify-content-center">
            <div class="col-12 mb-2">
                <div id="errorMsg" class="alert alert-danger d-none text-center w-100 m-0 rounded-0"></div>
            </div>

            <!-- INPUT BOX -->
            <div class="col-md-5">
                <div class="toolbar">
                    Paste Raw JSON Data
                </div>
                <textarea class="editor" id="inputJson" cols="51"></textarea>

            </div>
            <!-- SIDEBAR -->
            <div class="col-md-2 d-flex flex-column align-items-center justify-content-start">
                <div class="sidebar w-100 text-center">
                    <a href="../" class="hub-back w-100 justify-content-center mb-2" title="Back to Tool Hub"><svg viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>Tool Hub</a>
                    <!-- MODE SWITCH -->
                    <div class="btn-group w-100 mb-2" role="group">
                        <a href="index.php" class="btn btn-sm btn-outline-light">DECRYPT</a>
                        <a href="encrypt.php" class="btn btn-sm btn-light text-dark fw-bold">ENCRYPT</a>
                    </div>
                    <!-- Key management is public too, same as Decrypt/Encrypt -->
                    <button type="button" class="btn btn-success btn-sm" id="addKeyBtn">
                        ADD
                        <img src="add.svg" width="20" height="20" alt="add.svg">
                    </button>
                    <button type="button" id="decryptKey" class="btn btn-secondary btn-sm">
                        EDIT
                        <img src="edit.svg" width="20" height="20" alt="edit.svg">
                    </button>
                    <select name="projectName" class="form-select" id="projectSelect">
                        <option value="" selected>PRODUCT NAME</option>
                    </select>
                    <button type="button" class="btn btn-primary rounded-0 mb-2 btn-sm" id="encryptedData">ENCRYPT</button>
                    <button type="button" class="btn btn-danger rounded-0 btn-sm" id="clearInput" disabled>CLEAR</button>
                    <?php if (can("manage_users")): ?>
                    <a href="../users/" class="btn btn-sm btn-info w-100 mt-2">USERS</a>
                    <?php endif; ?>
                    <?php if ($me !== null): ?>
                    <a href="../common/logout.php" class="btn btn-sm btn-outline-light w-100 mt-2">LOGOUT (<?= h($me["username"]) ?>)</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- OUTPUT BOX -->
            <div class="col-md-5">
                <div class="toolbar d-flex align-items-center justify-content-between">
                    Encrypted Data
                    <div>
                        <!-- COPY BUTTON -->
                        <button id="copyOutput" class="btn btn-outline-secondary btn-sm ms-2" title="Copy Output"
                            style="padding:2px 8px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#fff"
                                class="bi bi-clipboard" viewBox="0 0 16 16">
                                <path
                                    d="M10 1.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1A1.5 1.5 0 0 1 7.5 0h1A1.5 1.5 0 0 1 10 1.5zm-1 1V1a.5.5 0 0 0-.5-.5h-1A.5.5 0 0 0 7 1v1.5h2z" />
                                <path
                                    d="M3.5 3A1.5 1.5 0 0 0 2 4.5v9A1.1 1.1 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5v-9A1.1 1.1 0 0 0 12.5 3h-9zm-1 1.5A.5.5 0 0 1 3.5 4h9a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5v-9z" />
                            </svg>
                        </button>
                        <!-- DOWNLOAD BUTTON -->
                        <button id="downloadOutput" class="btn btn-outline-success btn-sm ms-2" title="Download JSON"
                            style="padding:2px 8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"
                                stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 3v14"></path>
                                <polyline points="6 12 12 17 18 12"></polyline>
                                <rect x="4" y="19" width="16" height="2" rx="1"></rect>
                            </svg>
                        </button>
                    </div>
                </div>
                <textarea class="editor" id="outputJson" cols="51" readonly></textarea>
            </div>
        </div>
        <span style="font-size: 14px;font-weight: 600;color:indigo;">
            Copyright © Amit Vishwakarma <?= date('Y') ?>
            <span>
    </div>
    <!-- FULL FRONTEND JS -->
    <script>
        class JsonFormatter {

            constructor() {
                this.inputField = document.getElementById("inputJson");
                this.outputField = document.getElementById("outputJson");
                this.copyButton = document.getElementById("copyOutput");
                this.downloadButton = document.getElementById("downloadOutput");
                this.formatButton = document.getElementById("encryptedData");
                this.clearButton = document.getElementById("clearInput");
                this.loader = document.getElementById("outputLoader");

                this.init();
            }

            init() {
                document.addEventListener("DOMContentLoaded", () => {
                    this.hideLoader();
                    this.updateClearButtonState();
                });

                this.formatButton.addEventListener("click", (e) => this.handleChunkUpload(e));
                this.copyButton.addEventListener("click", () => this.copyOutput());
                this.downloadButton.addEventListener("click", () => this.downloadOutput());

                this.inputField.addEventListener("input", () => {
                    this.clearOutputIfInputEmpty();
                    this.updateClearButtonState();
                });

                this.clearButton.addEventListener("click", () => this.clearInput());
            }

            updateClearButtonState() {
                if (this.inputField.value.trim() === "") {
                    this.clearButton.setAttribute("disabled", "disabled");
                } else {
                    this.clearButton.removeAttribute("disabled");
                }
            }

            clearInput() {
                this.inputField.value = "";
                this.outputField.value = "";
                const selectedProject = document.getElementById("projectSelect").value = "";
                this.updateClearButtonState();
            }

            showLoader() {
                this.loader.style.display = "flex";
            }

            hideLoader() {
                this.loader.style.display = "none";
            }

            /** CHUNK UPLOAD */
            handleChunkUpload(event) {
                event.preventDefault();

                const inputValue = this.inputField.value.trim();
                const selectedProject = document.getElementById("projectSelect").value;
                //  Dropdown validation
                if (!selectedProject) {
                    Swal.fire({
                        icon: 'warning',
                        text: 'Select project name before encrypting.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,

                    });
                    return;
                }
                if (!inputValue) {
                    Swal.fire({
                        icon: 'warning',
                        text: 'Please paste JSON data before encrypting.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,

                    });
                    return;
                }

                this.showLoader();

                const CHUNK_SIZE = 50000;
                const totalLength = inputValue.length;
                const totalChunks = Math.ceil(totalLength / CHUNK_SIZE);
                let currentChunk = 0;

                const sendChunk = () => {
                    const start = currentChunk * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, totalLength);
                    const chunkData = inputValue.substring(start, end);

                    const formData = new FormData();
                    formData.append("chunk", chunkData);
                    formData.append("index", currentChunk);
                    formData.append("total", totalChunks);
                    // Dropdown value bhi send karo
                    formData.append("project_name", selectedProject);

                    fetch("server.php", {
                            method: "POST",
                            body: formData
                        })
                        .then(r => r.text())
                        .then(() => {
                            if (currentChunk + 1 === totalChunks) {
                                this.fetchEncryptedResult();
                            } else {
                                currentChunk++;
                                sendChunk();
                            }
                        })
                        .catch(err => {
                            this.hideLoader();
                            Swal.fire({
                                icon: 'error',
                                title: 'Upload Error',
                                text: err.message,
                                allowOutsideClick: false,
                                allowEscapeKey: false,

                            });
                        });
                };

                sendChunk();
            }

            /** FETCH final encrypted string */
            fetchEncryptedResult() {
                const selectedProject = document.getElementById("projectSelect").value;
                fetch("server.php?getFinal=1&mode=encrypt&project_name=" + encodeURIComponent(selectedProject), {
                        method: "GET",
                    })
                    .then(r => r.text())
                    .then(data => {
                        this.hideLoader();
                        let jsonDecode;
                        try {
                            jsonDecode = JSON.parse(data);
                        } catch {
                            return Swal.fire({
                                icon: 'error',
                                title: 'Parse Error',
                                text: 'Invalid JSON from server',
                                allowOutsideClick: false,
                                allowEscapeKey: false,

                            });
                        }

                        if (jsonDecode.status) {
                            // Encrypted output is a ciphertext string, not JSON
                            this.outputField.value = jsonDecode.data;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: jsonDecode.error,
                                allowOutsideClick: false,
                                allowEscapeKey: false,

                            });
                        }
                    });
            }

            /** COPY OUTPUT */
            copyOutput() {
                const outputValue = this.outputField.value.trim();

                if (!outputValue) {
                    Swal.fire({
                        icon: 'warning',
                        text: 'There is no encrypted data to copy.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,

                    });
                    return;
                }

                // Modern Clipboard API
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(outputValue)
                        .then(() => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Copied!',
                                text: 'Encrypted data copied to clipboard',
                                timer: 1200,
                                showConfirmButton: false,
                                allowOutsideClick: false,
                                allowEscapeKey: false,

                            });
                        })
                        .catch(() => this.fallbackCopy(outputValue));
                } else {
                    // Fallback for unsupported browsers
                    this.fallbackCopy(outputValue);
                }
            }

            fallbackCopy(text) {
                const temp = document.createElement("textarea");
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand("copy");
                document.body.removeChild(temp);

                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'Copied using fallback method.',
                    timer: 1200,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                });
            }
            /** DOWNLOAD OUTPUT */
            downloadOutput() {
                const outputValue = this.outputField.value.trim();

                if (!outputValue) {
                    Swal.fire({
                        icon: 'warning',
                        text: 'There is no encrypted data to download.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,

                    });
                    return;
                }

                const blob = new Blob([outputValue], {
                    type: "text/plain"
                });
                const url = URL.createObjectURL(blob);

                const a = document.createElement("a");
                a.href = url;
                a.download = "encrypted.txt";
                a.click();

                URL.revokeObjectURL(url);

                Swal.fire({
                    icon: 'success',
                    title: 'Downloaded!',
                    text: 'File downloaded successfully.',
                    timer: 1200,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                });
            }

            clearOutputIfInputEmpty() {
                if (this.inputField.value.trim() === "") {
                    this.outputField.value = "";
                }
            }
            addKey() {
                Swal.fire({
                    // title: 'ENTER PORDUCT ',
                    html: `<input type="text" id="key1" class="form-control mb-3 custom-input" placeholder="ENTER PRODUCT NAME" maxlength="20"><input type="text" id="key2" class="form-control mb-3 custom-input" placeholder="ENTER PRODUCT KEY" maxlength="50">`,

                    showCancelButton: true,
                    confirmButtonText: 'Submit',
                    focusConfirm: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false,

                    preConfirm: () => {

                        const key1 = document.getElementById('key1').value.trim();
                        const key2 = document.getElementById('key2').value.trim();
                        const regex = /^[A-Za-z_]+$/;
                        const regex2 = /^[A-Za-z0-9]+$/;

                        if (!key1 || !key2) {
                            Swal.showValidationMessage('Both fields are required!');
                            return false;
                        }

                        if (key1.length > 20) {
                            Swal.showValidationMessage('Maximum 10 characters allowed!');
                            return false;
                        }

                        if (!regex.test(key1)) {
                            Swal.showValidationMessage('Only letters allowed in project name!');
                            return false;
                        }

                        if (key2.length > 50) {
                            Swal.showValidationMessage('Maximum 50 characters allowed!');
                            return false;
                        }

                        if (!regex2.test(key2)) {
                            Swal.showValidationMessage('Only letters and numbers allowed in Key!');
                            return false;
                        }

                        return {
                            key1,
                            key2
                        };
                    }

                }).then((result) => {
                    if (result.isConfirmed) {

                        const formData = new FormData();
                        formData.append("csrf", document.querySelector('meta[name="csrf-token"]').content);
                        formData.append("project_name", result.value.key1);
                        formData.append("decryption_key", result.value.key2);

                        fetch("add.php", {
                                method: "POST",
                                body: formData
                            })
                            .then(r => r.text())
                            .then((response) => {
                                let responseJson = JSON.parse(response);
                                if (responseJson.status) {
                                    Swal.fire({
                                        icon: 'success',
                                        text: responseJson.message,
                                        allowOutsideClick: false,
                                        allowEscapeKey: false,

                                    }).then((e) => {
                                        this.buildSelectOption();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        text: responseJson.message,
                                        allowOutsideClick: false,
                                        allowEscapeKey: false,

                                    });
                                }
                            })
                            .catch(err => {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Upload Error',
                                    text: err.message,
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,

                                });
                            });
                    }
                });
            }
            buildSelectOption() {
                return fetch("getKeys.php")
                    .then(res => res.json())
                    .then(response => {
                        if (!response.status) return null;

                        const select = document.getElementById("projectSelect");
                        select.innerHTML = '<option value="">PRODUCT NAME</option>';

                        const data = response.data;

                        data.forEach(project => {

                            let option = document.createElement("option");
                            option.value = project.name; // id bhejna better hai
                            option.textContent = project.name;

                            select.appendChild(option);
                        });

                        return response;
                    });
            }
            updateKey() {
                const vm = this;
                this.buildSelectOption().then(response => {

                    if (!response.status) {
                        Swal.fire("Error", "No data found", "error");
                        return;
                    }

                    let tableHTML = `
                    <table class="project-table" border="1" cellspacing="0" cellpadding="8">
                        <thead>
                            <tr>
                                <th>PRODUCT NAME</th>
                                <th>KEYS</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>`;

                    response.data.forEach(project => {

                        tableHTML += `
                    <tr data-id="${project.id}">
                        <td class="project-name">${project.name}</td>
                        <td class="project-value">${project.keys}</td>
                        <td>
                            <img src="editIcon.svg"
                                width="25"
                                height="25"
                                class="edit-btn"
                                style="cursor:pointer;">
                        </td>
                    </tr>`;
                    });

                    tableHTML += `</tbody></table>`;

                    Swal.fire({
                        // title: "Project List",
                        html: tableHTML,
                        width: "800px",
                        confirmButtonText: "Close",
                        allowOutsideClick: false,
                        allowEscapeKey: false,

                        didOpen: () => {

                            document.querySelectorAll(".edit-btn").forEach(btn => {

                                btn.addEventListener("click", function() {

                                    let row = this.closest("tr");
                                    let rowId = row.dataset.id;

                                    let nameTd = row.querySelector(".project-name");
                                    let valueTd = row.querySelector(".project-value");

                                    if (row.querySelector("input")) return;

                                    let oldName = nameTd.innerText.trim();
                                    let oldValue = valueTd.innerText.trim();

                                    nameTd.innerHTML = `<input type="text"  class="form-control" value="${oldName}" style="width:100%">`;
                                    valueTd.innerHTML = `<input type="text" class="form-control" value="${oldValue}" style="width:100%">`;

                                    let nameInput = nameTd.querySelector("input");
                                    let valueInput = valueTd.querySelector("input");

                                    nameInput.focus();

                                    function saveData() {

                                        let newName = nameInput.value.trim();
                                        let newValue = valueInput.value.trim();

                                        // No change → skip API
                                        if (newName === oldName && newValue === oldValue) {
                                            nameTd.innerHTML = oldName;
                                            valueTd.innerHTML = oldValue;
                                            return;
                                        }

                                        nameTd.innerHTML = newName;
                                        valueTd.innerHTML = newValue;

                                        const formData = new FormData();
                                        formData.append("csrf", document.querySelector('meta[name="csrf-token"]').content);
                                        formData.append("id", rowId);
                                        formData.append("project_name", newName);
                                        formData.append("decryption_key", newValue);

                                        fetch("update.php", {
                                                method: "POST",
                                                body: formData
                                            })
                                            .then(res => res.json())
                                            .then(result => {

                                                if (result.status) {
                                                    Swal.fire({
                                                        icon: "success",
                                                        text: result.message,
                                                        allowOutsideClick: false,
                                                        allowEscapeKey: false
                                                    }).then(() => {
                                                        vm.buildSelectOption(); // ✅ dropdown refresh
                                                    });
                                                } else {
                                                    Swal.fire({
                                                        icon: "error",
                                                        text: result.message
                                                    });
                                                }

                                            })
                                            .catch(err => {
                                                Swal.fire({
                                                    icon: "error",
                                                    title: "Update Error",
                                                    text: err.message
                                                });
                                            });
                                    }

                                    [nameInput, valueInput].forEach(input => {

                                        input.addEventListener("keydown", (e) => {
                                            if (e.key === "Enter") {
                                                input.blur();
                                            }
                                        });

                                        input.addEventListener("blur", () => {
                                            setTimeout(() => {
                                                if (!row.querySelector(":focus")) {
                                                    saveData();
                                                }
                                            }, 100);
                                        });

                                    });

                                });

                            });

                        }
                    });

                });
            }
        }
        document.addEventListener("DOMContentLoaded", () => {
            const renderJosnObj = new JsonFormatter()
            renderJosnObj.buildSelectOption();
            const addKeyBtnClk = document.getElementById('addKeyBtn');
            if (addKeyBtnClk) {
                addKeyBtnClk.addEventListener('click', (e) => {
                    renderJosnObj.addKey();
                });
            }
            const decryptKey = document.getElementById('decryptKey');
            if (decryptKey) {
                decryptKey.addEventListener('click', (e) => {
                    renderJosnObj.updateKey();
                });
            }
        });
    </script>
</body>

</html>

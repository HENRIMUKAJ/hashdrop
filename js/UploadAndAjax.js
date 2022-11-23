function main(){
    // (C) INITIALIZE UPLOADER
    window.addEventListener("load", () => {
        // (C1) GET HTML FILE LIST
        var filelist = document.getElementById("filelist");

        // (C2) INIT PLUPLOAD
        var uploader = new plupload.Uploader({
            runtimes: "html5",
            browse_button: "pickfiles",
            url: "fileuploadhandler.php",
            chunk_size: "1024kb",
            multi_selection:false,
            filters:{
                max_file_size:"10gb"
            },
            init: {
                PostInit: () => {
                    filelist.innerHTML = "<div><h2 class='heading'>File To Be Uploaded:</h2></div>";
                },
                FilesAdded: (up, files) => {
                    plupload.each(files, (file) => {
                        let row = document.createElement("div");
                        row.id = file.id;
                        row.innerHTML = `${file.name}<img id="remove" class="delAlign" alt="remove file" src="./image/x.svg"> (${plupload.formatSize(file.size)}) <strong></strong>`;
                        filelist.appendChild(row);
                        let uploadBtn = document.getElementById("submitForm");
                        uploadBtn.removeAttribute("class");
                        uploadBtn.setAttribute("class", "button edit uploadBtn")
                        uploadBtn.disabled = false;
                        document.getElementById("pickfiles").disabled = true;
                        document.getElementById("remove").addEventListener("click", function () {
                            up.removeFile(file);
                        })
                    });
                    up.disableBrowse()
                    ValidateSpace(files[0].size);
                    if(files[0].size ===0)
                    {
                        document.querySelector("#serverRep").innerHTML = `<p class="dangerText"><h2 class="dangerText">File is too small to be uploaded</h2></p>`;
                        setTimeout(()=> {up.removeFile(files[0])},3000);
                        return false;
                    }
                    document.getElementById("submitForm").addEventListener("click", function () {
                        uploader.start();
                    });
                },
                UploadProgress: (up, file) => {
                    document.querySelector(`#${file.id} strong`).innerHTML = `${file.percent}%`;
                },
                FilesRemoved:(up,file) => {
                    up.disableBrowse(false)
                    document.getElementById(file[0].id).remove()
                    document.getElementById("pickfiles").disabled = false;
                    document.getElementById("submitForm").disabled = true;
                    document.getElementsByClassName("dangerText")[1].remove();
                },
                Error: (up, err) => {
                    console.log(err)
                    if(err.message !== "" && err.code === -600){
                        document.querySelector("#serverRep").innerHTML = `<p class="dangerText">${err.message}File size limit is 10gb!</p>`;
                        return false;
                    }
                    document.querySelector("#serverRep").innerHTML = `<p class="dangerText">${err.response}</p>`;
                },
                FileUploaded: (uploader,files,result)=> {
                    let serverOutput = document.querySelector("#serverRep");
                    serverOutput.innerHTML = result.response;
                    document.getElementById("pickfiles").style.display = "none";
                    document.getElementById("submitForm").style.display = "none";
                    document.getElementById("pickfiles").disabled = true;
                    document.getElementById("submitForm").disabled = true;
                    let formData = document.getElementById("submitForm").value;

                    if (result.status == 200) {
                        let fileFormData = new FormData();
                        fileFormData.append("SubmitUploadForm", formData);
                        var httpc = new XMLHttpRequest();
                        var url = "CRUDHashDropDB.php";
                        httpc.open("POST", url, true);
                        httpc.onreadystatechange = function () {
                            if (httpc.readyState == 4 && httpc.status == 200) {
                                window.location.href = "index.php";
                            }
                        };
                        httpc.send(fileFormData);
                    }
                }
            },
        });
        uploader.init();
    });
}

function ValidateSpace(size)
{
    var http = new XMLHttpRequest();
    http.open("GET","FormValidation.php?fileSize="+size,true)
    http.onreadystatechange=function (){
        if(this.readyState=== 4 && this.status === 400){
            document.querySelector("#serverRep").innerHTML = `<p class="dangerText">${http.response}</p>`;
        }
    }
    http.send();
}

main();
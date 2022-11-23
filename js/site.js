const openModalButtons = document.querySelectorAll('[data-modal-target]');
const closeModalButtons = document.querySelectorAll('[data-close-button]');
const overlay = document.getElementById('overlay');
const editForm = document.getElementsByClassName("Edit-Form");
const emailField = document.getElementById("receiverEmail");
const fileDesc = document.getElementById("fileDescription");
const timeField = document.getElementById("timetildel");
const submitBtn = document.getElementById("insertInfo");
const observerConfig= {childList:true}

if(submitBtn != null){
    submitBtn.disabled=true
}

window.onload = () => {
    sendSpaceReq();
}


if(timeField != null){
    timeField.addEventListener("input", function () {
        if(timeField.value === "Custom Input")
        {
            replaceUploadSelect();
            setTimeout(ValidateForm,500,timeField.value,"FormValidation.php?CheckDropDown=","timeError");
            return true
        }
        setTimeout(function(){ ValidateForm(timeField.value, "FormValidation.php?CheckDropDown=", "timeError")},1000);
    })
}

if(fileDesc != null){
    fileDesc.addEventListener("input", function () {
        setTimeout(function(){ ValidateForm(fileDesc.value, "FormValidation.php?CheckDescription=", "descError")},1000);
    })
}

if(emailField != null) {
    emailField.addEventListener("input", function () {
        setTimeout(function(){ ValidateForm(emailField.value, "FormValidation.php?ValidateEmail=", "emailError")},4000);
    })
}

function checkEditSpan(){
    const timeError=document.getElementsByClassName("dropdownerror");
    const fileNameError=document.getElementsByClassName("filenameerror");
    const descError=document.getElementsByClassName("filedescerror");
    if(timeError[0].innerHTML === "" && fileNameError[0].innerHTML === "" && descError[0].innerHTML === ""){
        return true;
    }
    else{
        return false;
    }

}

function checkUploadSpan(){
    const timeError=document.getElementsByClassName("timeError");
    const emailError=document.getElementsByClassName("emailError");
    const descError=document.getElementsByClassName("descError");
    if(timeError[0].innerHTML === "" && emailError[0].innerHTML === "" && descError[0].innerHTML === ""){
        return true;
    }
    else{
      return false;
    }

}

function replaceUploadSelect(){
    const selectElem=document.getElementById("timetildel");
    selectElem.disabled =true;
    selectElem.hidden=true;
    const svgImage=document.getElementById("svgImage");
    svgImage.hidden=false;
    selectElem.setAttribute("class","dropinput");
    const newInput=document.createElement('input');
    newInput.setAttribute("type","text");
    newInput.setAttribute("id","timetildel2");
    newInput.setAttribute("name","timetildel");
    newInput.setAttribute("class","inputField dropinput");
    let parentDiv=document.getElementById("replace")
    parentDiv.appendChild(newInput);
    let elemArange= document.getElementsByName("timetildel");
    let customInput=document.getElementById("timetildel2")
    parentDiv.insertBefore(elemArange[1],elemArange[0]);
    customInput.addEventListener("input",()=>{
        setTimeout(ValidateForm,500,customInput.value,"FormValidation.php?CheckDropDown=","timeError")
    })
    svgImage.addEventListener("click",()=>{
        replaceInput("timetildel")
    })
}

openModalButtons.forEach(button => {
    button.addEventListener('click',(event) => {
        const modal = document.querySelector(button.dataset.modalTarget);
        sendEditVal(event.target.value);
        openModal(modal);
    })
})

closeModalButtons.forEach(button =>{
    button.addEventListener('click',()=> {
        const modal = button.closest('.modal');
        closeModal(modal);
    })
})

function sendSpaceReq(){
    var http = new XMLHttpRequest();
    http.open("GET","FormValidation.php?GetSpace",true);
    http.onreadystatechange=function (){
        if(this.readyState==4 && this.status ==200){
            let serverOutput = document.getElementById("AvalSize");
            serverOutput.innerHTML = http.responseText;
        }
    }
    http.send();
}

function sendEditVal(value){
    var http = new XMLHttpRequest();
    http.open("GET","FormValidation.php?Edit="+value,true)
    http.onreadystatechange=function (){
        if(this.readyState==4 && this.status ==200){
            let serverOutput = document.querySelector("#modal").getElementsByClassName("Edit-Form")[0];
            serverOutput.innerHTML = http.responseText;
        }
    }
    http.send(value);
}


const mutationCall= (mutations,observer) => {
    for (const possibleMutations of mutations){
        if(possibleMutations.type === 'childList') {
            const nameinput = document.getElementsByClassName("nameinput");
            const descinput = document.getElementsByClassName("descinput");
            const dropinput = document.getElementsByClassName("dropinput");
            const emailinput = document.getElementsByClassName("emailinput")
            emailinput[0].addEventListener("input",()=>{
                setTimeout(function(){ ValidateForm(emailinput[0].value, "FormValidation.php?ValidateEmail=", "emailserror")},1000);
            })
            nameinput[0].addEventListener("input",()=>{
                ValidateForm(nameinput[0].value,"FormValidation.php?CheckFileName=","filenameerror")
            })
            descinput[0].addEventListener("input",()=>{
               setTimeout(ValidateForm,100,descinput[0].value,"FormValidation.php?CheckDescription=","filedescerror");
            })
            dropinput[0].addEventListener("input",()=>{
                if(dropinput[0].value === "Custom Input")
                {
                    replaceSelect();
                    setTimeout(ValidateForm,500,dropinput[0].value,"FormValidation.php?CheckDropDown=","dropdownerror");
                    return true
                }
                setTimeout(ValidateForm,500,dropinput[0].value,"FormValidation.php?CheckDropDown=","dropdownerror");
            })
        }
    }
}

function CheckCurrentEditFormVals()
{
    const fileNameField = document.getElementById("filename1");
    const fileDesc = document.getElementById("filedescr1");
    const timeField = document.getElementById("timetildel1");
    const emailField = document.getElementById("receiverEmail");
    if(fileNameField.value === "" || fileDesc.value === "" || timeField.value === "" || emailField.value === ""){
        return false
    }
    return  true
}

function CheckCurrentUploadFormVals()
{
    const emailField = document.getElementById("receiverEmail");
    const fileDesc = document.getElementById("fileDescription");
    const timeField = document.getElementById("timetildel");
    if(emailField.value === "" || fileDesc.value === "" || timeField.value === ""){
        return false
    }
    return  true
}

function replaceSelect(){
    const selectElem=document.getElementById("timetildel1");
    selectElem.disabled =true;
    selectElem.hidden=true;
    const svgImage=document.getElementById("svgImage");
    svgImage.hidden=false;
    selectElem.setAttribute("class","dropinput");
    const newInput=document.createElement('input');
    newInput.setAttribute("type","text");
    newInput.setAttribute("id","timetildel2");
    newInput.setAttribute("name","timetildel1");
    newInput.setAttribute("class","inputField dropinput");
    let parentDiv=document.getElementById("replace")
    parentDiv.appendChild(newInput);
    let elemArange= document.getElementsByName("timetildel1");
    let customInput=document.getElementById("timetildel2")
    parentDiv.insertBefore(elemArange[1],elemArange[0]);
    customInput.addEventListener("input",()=>{
        setTimeout(ValidateForm,500,customInput.value,"FormValidation.php?CheckDropDown=","dropdownerror")
    })
    svgImage.addEventListener("click",()=>{
        replaceInput("timetildel1")
    })
}

const observer = new MutationObserver(mutationCall);

if(editForm[0] !=null) {
    observer.observe(editForm[0], observerConfig)
}

function replaceInput(elementId){
    const svgImage=document.getElementById("svgImage");
    const selectElem=document.getElementById(elementId);
    const customInput=document.getElementById("timetildel2");
    let parentDiv=document.getElementById("replace");

    if(customInput != null) {
        parentDiv.replaceChild(selectElem, customInput)
    }
    selectElem.hidden=false;
    selectElem.disabled=false;
    selectElem.setAttribute("class","inputField dropinput");
    svgImage.hidden=true;
}

function ValidateForm(formData,url,errorLoc){
    const Editbtn=document.getElementById("Edit")
    const submitBtn=document.getElementById("insertInfo");
    var http = new XMLHttpRequest();
    http.open("GET",url+formData,true)
    http.onreadystatechange=function (){
        if (http.readyState == 4 && http.status == 200) { // complete and no errors
            let serverOutput = document.getElementsByClassName(errorLoc)[0];
            serverOutput.innerHTML = http.responseText;
            if(Editbtn != null){
                if(checkEditSpan()===true && CheckCurrentEditFormVals()===true){
                    Editbtn.disabled = false;
                }
                else{
                    Editbtn.disabled=true;
                }
            }
            if(submitBtn !=null){
                if(checkUploadSpan()===true && CheckCurrentUploadFormVals()===true){
                    submitBtn.disabled=false;
                }
                else{
                    submitBtn.disabled=true;
                }
            }
            return true;
        }
        if(this.status == 400){
            let serverOutput = document.getElementsByClassName(errorLoc)[0];
            serverOutput.innerHTML = http.responseText;
                if(Editbtn != null){
                    Editbtn.disabled = true;
                }
                if(submitBtn !=null){
                    submitBtn.disabled=true;
                }
            return false;
        }
    }
    http.send();
}

closeModalButtons.forEach(button =>{
    button.addEventListener('click',()=> {
        const modal = button.closest('.modal');
        closeModal(modal);
    })
})

function openModal(modal){
    if(modal == null) return
    modal.classList.add('active');
    overlay.classList.add('active');
}

function closeModal(modal){
    if(modal == null) return
    modal.classList.remove('active');
    overlay.classList.remove('active');
}

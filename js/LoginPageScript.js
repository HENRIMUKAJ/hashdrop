function ShowLabel(input, label, row, container)
{
    document.getElementById(input).style.height = '30px';
    document.getElementById(input).style.paddingTop = '0px';
    document.getElementById(label).style.visibility = 'visible';
    document.getElementById(row).style.visibility = 'visible';
    document.getElementById(container).style.border = '2px solid rgb(30, 144, 255)';
}

function HideLabel(input, label, row, container)
{
    if (document.getElementById(input).value == '')
    {
        document.getElementById(input).style.height = '56px';
        document.getElementById(input).style.paddingTop = '8px';
        document.getElementById(label).style.visibility = 'hidden';
        document.getElementById(row).style.visibility = 'collapse';
        document.getElementById(container).style.border = '1px solid gray';
    }
}

function DarkenButton(input)
{
    if (document.getElementById('username_input').value != '' && document.getElementById('password_input').value != '')
    {
        document.getElementById('logon_btn').style.pointerEvents = 'all';
        document.getElementById('logon_btn').style.backgroundColor = 'rgb(30, 144, 255)';
        document.getElementById('logon_btn').style.cursor = 'pointer';
    }
    else
    {
        document.getElementById('logon_btn').style.pointerEvents = 'none';
        document.getElementById('logon_btn').style.backgroundColor = 'rgba(30,144,255, 0.719)';
        document.getElementById('logon_btn').style.cursor = 'auto';
    }
}


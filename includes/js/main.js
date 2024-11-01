const tabBtns=document.querySelectorAll(".tab-btn"),tabsContainer=document.querySelector(".tabs");tabBtns&&tabBtns.forEach(btn=>btn.addEventListener("click",e=>{const textValue=btn.textContent;tabsContainer.dataset.activeTab=textValue,tabBtns.forEach(b=>{if(b!==btn){b.classList.remove("text-main","after:bg-main"),b.classList.add("text-gray-500");return}}),btn.classList.add("text-main","after:bg-main")}));
const storageVariantsInputs=document.querySelectorAll(".storage-picker"),storageVariants=document.querySelectorAll(".storage"),[local,ftpSftp,cloud]=storageVariants,updateStorageVisibility=node=>{storageVariants.forEach(storage=>{storage.classList.remove("flex"),storage.classList.add("hidden")}),node.classList.remove("hidden"),node.classList.add("flex")};storageVariantsInputs.forEach((radio,index)=>radio.addEventListener("change",e=>{switch(index){case 0:updateStorageVisibility(local);break;case 1:updateStorageVisibility(ftpSftp);break;case 2:updateStorageVisibility(cloud);break}}));const filesExtensionsInput=document.querySelector("#files-extensions"),extensionsContainer=document.querySelector(".extensions-container"),addExtension=ext=>{const span=document.createElement("span");span.classList.add("extension","text-sm","rounded-xl","bg-blue-100","px-3","py-0.5","font-medium","text-blue-800"),span.dataset.value=ext,span.textContent=ext,extensionsContainer.append(span)};
jQuery(document).ready(function ($) {
    $('body').on('click', '.wt_backups_alert__close', function () {
        $(this).parent('.wt_backups_alert').remove();
    });


    var tab = $('#tabs .tabs-items > div');
    tab.hide().filter(':first').show();

    // Clicks on tabs.
    $('#tabs .tabs-nav label').click(function(){
        tab.hide();
        $(this).find('input').prop('checked', true);
        tab.filter('#' + $(this).data('id')).show();
        return false;
    }).filter(':first').click();


    $("input[name='backup_storage']").click(function(e){
        if($(this).attr('id') === 'backup-storage-cloud'){
            $('#storage_buttons').hide();
        } else {
            $('#storage_buttons').show();
        }
    });

});


<?php
declare(strict_types=1);

return [
    'companies' => [
        ['id'=>'company-001','name'=>'Constructora Norte S.A.C.','ruc'=>'20123456789','phone'=>'044 610 101','email'=>'contacto@constructoranorte.demo','address'=>'Av. Industrial 120, Trujillo','observations'=>'Empresa demo activa.','active'=>true,'created_at'=>'2026-06-20 09:00:00','updated_at'=>'2026-06-20 09:00:00'],
        ['id'=>'company-002','name'=>'Ingeniería Andina S.A.C.','ruc'=>'20456789120','phone'=>'044 610 102','email'=>'administracion@ingenieriaandina.demo','address'=>'Calle Los Álamos 250, Trujillo','observations'=>'','active'=>true,'created_at'=>'2026-06-20 09:10:00','updated_at'=>'2026-06-20 09:10:00'],
        ['id'=>'company-003','name'=>'Transportes El Sol S.A.C.','ruc'=>'20511843724','phone'=>'044 610 103','email'=>'operaciones@transporteselsol.demo','address'=>'Carretera Industrial km 4, Trujillo','observations'=>'Operador tributario de un consorcio demo.','active'=>true,'created_at'=>'2026-06-20 09:20:00','updated_at'=>'2026-06-20 09:20:00'],
        ['id'=>'company-004','name'=>'Comercial Valle Norte E.I.R.L.','ruc'=>'20601856345','phone'=>'044 610 104','email'=>'contacto@valle-norte.demo','address'=>'Jr. Independencia 318, Trujillo','observations'=>'','active'=>true,'created_at'=>'2026-06-20 09:30:00','updated_at'=>'2026-06-20 09:30:00'],
    ],
    'consortia' => [
        ['id'=>'consortium-001','name'=>'Consorcio Vías del Norte','mode'=>'ruc_propio','ruc'=>'20698765432','participants'=>[
            ['company_id'=>'company-001','active'=>true,'role_label'=>'Participante','is_tax_operator'=>false,'added_at'=>'2026-06-21 10:00:00','removed_at'=>'','removal_reason'=>''],
            ['company_id'=>'company-002','active'=>true,'role_label'=>'Participante','is_tax_operator'=>false,'added_at'=>'2026-06-21 10:00:00','removed_at'=>'','removal_reason'=>''],
        ],'observations'=>'Consorcio demo con RUC propio.','active'=>true,'created_at'=>'2026-06-21 10:00:00','updated_at'=>'2026-06-21 10:00:00'],
    ],
    'contacts' => [
        ['id'=>'contact-001','full_name'=>'Carla Valladares','mobile'=>'949 510 201','email'=>'carla.valladares@constructoranorte.demo','document_type'=>'DNI','document'=>'45871236','label'=>'Coordinadora administrativa','observations'=>'','relationships'=>[['entity_id'=>'company-001','entity_type'=>'Empresa','label'=>'Contacto comercial','is_primary'=>true,'active'=>true]],'active'=>true],
        ['id'=>'contact-003','full_name'=>'Mariela Castañeda','mobile'=>'949 510 203','email'=>'mariela.castaneda@consorciovias.demo','document_type'=>'DNI','document'=>'40981257','label'=>'Apoderada','observations'=>'','relationships'=>[['entity_id'=>'consortium-001','entity_type'=>'Consorcio','label'=>'Contacto principal','is_primary'=>true,'active'=>true]],'active'=>true],
    ],
];

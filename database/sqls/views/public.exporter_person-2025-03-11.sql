create view public.exporter_person
AS SELECT p.idpes AS id,
          p.nome AS name,
          f.nome_social AS social_name,
          trim(to_char(f.cpf, '000"."000"."000"-"00')) AS cpf,
          d.rg,
          d.data_exp_rg AS rg_issue_date,
          d.sigla_uf_exp_rg AS rg_state_abbreviation,
          f.data_nasc AS date_of_birth,
          p.email,
          f.sus,
          f.nis_pis_pasep AS nis,
          f.ocupacao AS occupation,
          f.empresa AS organization,
          f.renda_mensal AS monthly_income,
          f.sexo AS gender,
          r.nm_raca AS race,
          f.idpes_mae AS mother_id,
          f.idpes_pai AS father_id,
          f.idpes_responsavel AS guardian_id,
          case f.nacionalidade
              when 1 then 'Brasileira'::varchar
              when 2 then 'Naturalizado brasileiro'::varchar
              when 3 then 'Estrangeira'::varchar
              else 'Não informado'::varchar
          end as nationality,
          COALESCE( ci."name"||' - '||st.abbreviation , 'Não informado') as birthplace,
          re.name AS religion,
          case f.localizacao_diferenciada
              when 1 then 'Área de assentamento'
              when 2 then 'Terra indígena'
              when 3 then 'Comunidade quilombola'
              when 8 then 'Área onde se localizam povos e comunidades tradicionais'
              when 7 then 'Não está em área de localização diferenciada'
              else 'Não informado'
          end as localization_type
   FROM cadastro.pessoa p
     JOIN cadastro.fisica f ON f.idpes = p.idpes
     LEFT JOIN cadastro.fisica_raca fr ON fr.ref_idpes = f.idpes
     LEFT JOIN cadastro.raca r ON r.cod_raca = fr.ref_cod_raca
     LEFT JOIN cadastro.documento d ON d.idpes = p.idpes
     LEFT JOIN public.cities ci ON ci.id = f.idmun_nascimento
     LEFT JOIN public.states st on ci.state_id = st.id
     LEFT JOIN pmieducar.religions re on re.id = f.ref_cod_religiao
  WHERE true AND f.ativo = 1;

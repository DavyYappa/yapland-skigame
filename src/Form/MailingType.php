<?php

namespace App\Form;

use App\Entity\Mailing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MailingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, ['label' => 'Onderwerp', 'empty_data' => ''])
            ->add('body', TextareaType::class, [
                'label' => 'Tekst',
                'empty_data' => '',
                'attr' => ['rows' => 9],
                'help' => '{bedrijf} wordt vervangen door de naam van het bedrijf. Een lege regel begint een nieuwe alinea.',
            ])
            ->add('buttonLabel', TextType::class, ['label' => 'Tekst op de knop', 'empty_data' => '']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Mailing::class]);
    }
}

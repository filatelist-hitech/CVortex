import AccessShell from "../../access-shell";

export default async function VacancyDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <AccessShell destination="vacancies" initialVacancyId={id} />;
}

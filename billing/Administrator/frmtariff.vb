Public Class frmtariff 

    Private Sub btn2_Click(sender As Object, e As EventArgs) Handles btn2.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Tariff Code: " + txtTarrifCode.Text + "?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            deletetariff()
        End If
    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        txtTarrifCode.Text = ""
        txtTarrifDesc.Text = ""
        txtTarrifBasis.Text = ""
        txtTarrifUnit.Text = ""
        cbTarrifClass.Text = ""
        darrastre.Text = ""
        dstevedor.Text = ""
        dother.Text = ""
        farrastre.Text = ""
        fstevedor.Text = ""
        fother.Text = ""
        cbTarrifServiceCode.Text = ""
        cbAlias.Text = ""
    End Sub

    Private Sub frmtariff_FormClosed(sender As Object, e As FormClosedEventArgs) Handles Me.FormClosed
        Me.Dispose()
    End Sub

    Private Sub frmtariff_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        viewservicecode()
        cbTarrifClass.Text = "CONTAINERIZED"
        viewaliasname(cbTarrifServiceCode.Text)
        searchalias()
        If (myCon.State = ConnectionState.Open) Then
            myCon.close()
        End If
    End Sub

    Private Sub txtTarrifCode_TextChanged(sender As Object, e As EventArgs) Handles txtTarrifCode.TextChanged
        ' searchalias()
        searchtariff()
        viewaliasname(cbTarrifServiceCode.Text)
        searchalias()
    End Sub

    Private Sub cbTarrifServiceCode_TextChanged(sender As Object, e As EventArgs) Handles cbTarrifServiceCode.TextChanged
        viewaliasname(cbTarrifServiceCode.Text)
        searchalias()
    End Sub

    Private Sub btn1_Click(sender As Object, e As EventArgs) Handles btn1.Click
        If btn1.Text = "Register" Then
            Dim result As Integer = MessageBox.Show("Are you sure you want to Register this Tariff Code: " + txtTarrifCode.Text + "?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                registertariff()
            End If
        ElseIf btn1.Text = "Update" Then
            Dim result As Integer = MessageBox.Show("Are you sure you want to Update this Tariff Code: " + txtTarrifCode.Text + "?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                updatetariff()
            End If
        End If
    End Sub

    Private Sub darrastre_KeyPress(sender As Object, e As KeyPressEventArgs) Handles darrastre.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub darrastre_TextChanged(sender As Object, e As EventArgs) Handles darrastre.TextChanged

    End Sub

    Private Sub dstevedor_KeyPress(sender As Object, e As KeyPressEventArgs) Handles dstevedor.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub dstevedor_TextChanged(sender As Object, e As EventArgs) Handles dstevedor.TextChanged

    End Sub

    Private Sub dother_KeyPress(sender As Object, e As KeyPressEventArgs) Handles dother.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub dother_TextChanged(sender As Object, e As EventArgs) Handles dother.TextChanged

    End Sub

    Private Sub farrastre_KeyPress(sender As Object, e As KeyPressEventArgs) Handles farrastre.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub farrastre_TextChanged(sender As Object, e As EventArgs) Handles farrastre.TextChanged

    End Sub

    Private Sub fstevedor_KeyPress(sender As Object, e As KeyPressEventArgs) Handles fstevedor.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub fstevedor_TextChanged(sender As Object, e As EventArgs) Handles fstevedor.TextChanged

    End Sub

    Private Sub fother_KeyPress(sender As Object, e As KeyPressEventArgs) Handles fother.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub fother_TextChanged(sender As Object, e As EventArgs) Handles fother.TextChanged

    End Sub
End Class